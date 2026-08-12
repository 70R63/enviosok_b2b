<?php

namespace App\Domain\Network\Onboarding\Services;

use App\Domain\ApiHub\Models\TenantApiQuotaPolicy;
use App\Domain\Network\Billing\Models\{Entitlement, Subscription};
use App\Domain\Network\Catalog\Models\{Module, Plan};
use App\Domain\Network\Commerce\Models\{
    NetworkCommercialProduct, PlatformPaymentAttempt, TenantOperationAllowance, TenantSaasOrder
};
use App\Domain\Network\Onboarding\Exceptions\OnboardingProvisioningException;
use App\Domain\Network\Onboarding\Models\SaasOnboardingApplication;
use App\Domain\Network\Tenancy\Models\{Tenant, TenantDomain};
use App\Models\{Empresa, User};
use Illuminate\Support\Facades\{DB, Hash, Schema};
use Illuminate\Support\Str;
use Throwable;

final class SaasTenantProvisioningService
{
    public function __construct(
        private OnboardingStateService $states,
        private OnboardingMetadataSanitizer $sanitizer,
        private OnboardingSubdomainService $subdomains,
        private LegacyEmpresaAdapter $legacyCompanies,
        private OwnerActivationService $ownerActivations,
    ) {}

    public function provision(SaasOnboardingApplication|int $application): SaasOnboardingApplication
    {
        $application = $application instanceof SaasOnboardingApplication
            ? $application->fresh()
            : SaasOnboardingApplication::findOrFail($application);
        if ($application->status === SaasOnboardingApplication::ACTIVE) {
            $this->ownerActivations->sendIfNeeded($application);
            return $application;
        }
        if ($application->status === SaasOnboardingApplication::PROVISIONING) {
            return $application;
        }
        if (!in_array($application->status, [SaasOnboardingApplication::PAID, SaasOnboardingApplication::FAILED], true)) {
            throw new OnboardingProvisioningException('ONBOARDING_NOT_PROVISIONABLE');
        }

        $retried = $application->status === SaasOnboardingApplication::FAILED;
        $application = $this->states->transition(
            $application,
            SaasOnboardingApplication::PROVISIONING,
            $retried ? 'PROVISIONING_RETRIED' : 'PROVISIONING_STARTED',
            'system',
            correlationKey: $this->correlation($application, $retried ? 'retry' : 'start'),
        );

        try {
            $application = $this->prepareLegacyCompany($application);
            $active = DB::transaction(function () use ($application): SaasOnboardingApplication {
                $onboarding = SaasOnboardingApplication::query()
                    ->whereKey($application->id)->lockForUpdate()->firstOrFail();
                if ($onboarding->status === SaasOnboardingApplication::ACTIVE) return $onboarding;
                if ($onboarding->status !== SaasOnboardingApplication::PROVISIONING || !$onboarding->paid_at) {
                    throw new OnboardingProvisioningException('PAID_STATE_REQUIRED');
                }

                $snapshot = $onboarding->commercial_snapshot_json;
                if (!$snapshot || empty($snapshot['plan']['id'])) {
                    throw new OnboardingProvisioningException('COMMERCIAL_SNAPSHOT_INVALID');
                }
                $plan = Plan::find($snapshot['plan']['id']);
                if (!$plan || $plan->code !== $snapshot['plan']['code']) {
                    throw new OnboardingProvisioningException('SNAPSHOT_PLAN_MISSING');
                }

                $existingOwner = User::whereRaw('LOWER(email) = ?', [$onboarding->contact_email])->first();
                $legacyCompany = Empresa::withoutGlobalScopes()->find($onboarding->legacy_empresa_id);
                if (!$legacyCompany) {
                    throw new OnboardingProvisioningException('LEGACY_COMPANY_MISSING');
                }

                $owner = $existingOwner;
                if (!$owner) {
                    $owner = User::create([
                        'name' => $onboarding->contact_name,
                        'apellido_paterno' => $onboarding->contact_last_name,
                        'email' => $onboarding->contact_email,
                        'empresa_id' => $legacyCompany->id,
                        'password' => Hash::make(Str::random(64)),
                    ]);
                    $this->audit($onboarding, 'OWNER_CREATED');
                    if (Schema::hasColumn('saas_onboarding_applications', 'owner_is_new')) {
                        $onboarding->update(['owner_is_new' => true]);
                    }
                } else {
                    $this->audit($onboarding, 'OWNER_REUSED');
                    if (Schema::hasColumn('saas_onboarding_applications', 'owner_is_new')) {
                        $onboarding->update(['owner_is_new' => false]);
                    }
                }
                $onboarding->update(['owner_user_id' => $owner->id]);

                $tenant = $this->tenant($onboarding, $plan);
                $onboarding->update(['tenant_id' => $tenant->id]);

                $membership = $tenant->memberships()->updateOrCreate(
                    ['user_id' => $owner->id],
                    ['role' => 'owner', 'status' => 'active'],
                );
                $this->audit($onboarding, 'MEMBERSHIP_CREATED', ['membership_id' => $membership->id]);

                $tenant->branding()->updateOrCreate([], ['brand_name' => $onboarding->company_name]);
                $this->audit($onboarding, 'BRANDING_CREATED');

                $order = $this->commercialOrder($onboarding, $tenant, $owner, $snapshot);
                $subscription = $this->subscription($onboarding, $tenant, $plan, $snapshot);
                $this->entitlements($onboarding, $tenant, $subscription, $snapshot, $order);
                $this->allowance($onboarding, $tenant, $subscription, $snapshot, $order);
                $domain = $this->domain($onboarding, $tenant);

                $this->assertInvariants($onboarding->fresh(), $tenant->fresh(), $owner, $plan, $subscription, $snapshot, $domain);
                $tenant->update(['status' => 'active', 'current_plan_id' => $plan->id]);
                $active = $this->states->transition(
                    $onboarding->fresh(),
                    SaasOnboardingApplication::ACTIVE,
                    'PROVISIONING_COMPLETED',
                    'system',
                    correlationKey: $this->correlation($onboarding, 'completed'),
                );
                return $active;
            });
            $this->ownerActivations->sendIfNeeded($active);
            return $active;
        } catch (Throwable $exception) {
            $fresh = SaasOnboardingApplication::findOrFail($application->id);
            if ($fresh->status === SaasOnboardingApplication::PROVISIONING) {
                $code = $exception instanceof OnboardingProvisioningException
                    ? $exception->failureCode
                    : 'PROVISIONING_ERROR';
                $fresh = $this->states->transition(
                    $fresh,
                    SaasOnboardingApplication::FAILED,
                    'PROVISIONING_FAILED',
                    'system',
                    correlationKey: $this->correlation($fresh, 'failed'),
                    metadata: [
                        'failure_code' => $code,
                        'failure_context' => ['exception_class' => get_class($exception)],
                    ],
                );
            }
            return $fresh;
        }
    }

    private function prepareLegacyCompany(SaasOnboardingApplication $application): SaasOnboardingApplication
    {
        return DB::transaction(function () use ($application): SaasOnboardingApplication {
            $onboarding = SaasOnboardingApplication::query()
                ->whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($onboarding->status !== SaasOnboardingApplication::PROVISIONING || !$onboarding->paid_at) {
                throw new OnboardingProvisioningException('PAID_STATE_REQUIRED');
            }
            $snapshot = $onboarding->commercial_snapshot_json;
            $plan = !empty($snapshot['plan']['id']) ? Plan::find($snapshot['plan']['id']) : null;
            if (!$snapshot || !$plan || $plan->code !== ($snapshot['plan']['code'] ?? null)) {
                throw new OnboardingProvisioningException('COMMERCIAL_SNAPSHOT_INVALID');
            }

            $existingOwner = User::whereRaw('LOWER(email) = ?', [$onboarding->contact_email])->first();
            [$legacyCompany, $companyCreated] = $this->legacyCompanies->resolveOrCreate($onboarding, $existingOwner);
            $onboarding->update(['legacy_empresa_id' => $legacyCompany->id]);
            $this->audit($onboarding, $companyCreated ? 'LEGACY_COMPANY_CREATED' : 'LEGACY_COMPANY_REUSED');

            return $onboarding->fresh();
        });
    }

    private function tenant(SaasOnboardingApplication $onboarding, Plan $plan): Tenant
    {
        if ($onboarding->tenant_id) {
            $tenant = Tenant::find($onboarding->tenant_id);
            if (!$tenant || $tenant->slug !== $onboarding->requested_subdomain) {
                throw new OnboardingProvisioningException('TENANT_LINK_INVALID');
            }
            return $tenant;
        }
        if (Tenant::where('slug', $onboarding->requested_subdomain)->exists()) {
            throw new OnboardingProvisioningException('TENANT_SLUG_CONFLICT');
        }
        $tenant = Tenant::create([
            'name' => $onboarding->company_name,
            'slug' => $onboarding->requested_subdomain,
            'status' => 'inactive',
            'current_plan_id' => $plan->id,
        ]);
        $this->audit($onboarding, 'TENANT_CREATED', ['tenant_uuid' => $tenant->uuid]);
        return $tenant;
    }

    private function commercialOrder($onboarding, Tenant $tenant, User $owner, array $snapshot): TenantSaasOrder
    {
        $productId = $snapshot['plan']['commercial_product_id'] ?? null;
        $product = $productId ? NetworkCommercialProduct::find($productId) : null;
        if (!$product) throw new OnboardingProvisioningException('COMMERCIAL_PRODUCT_MISSING');
        $attempt = PlatformPaymentAttempt::where('onboarding_application_id', $onboarding->id)
            ->where('status', 'APPROVED')->first();
        if (!$attempt?->provider_payment_id) throw new OnboardingProvisioningException('APPROVED_PAYMENT_MISSING');

        return TenantSaasOrder::updateOrCreate(
            ['onboarding_application_id' => $onboarding->id],
            [
                'tenant_id' => $tenant->id, 'commercial_product_id' => $product->id,
                'created_by_user_id' => $owner->id, 'purchase_key' => $onboarding->purchase_key,
                'status' => 'ACTIVATED', 'payment_status' => 'APPROVED', 'quantity' => 1,
                'unit_amount' => $onboarding->subtotal, 'subtotal' => $onboarding->subtotal,
                'tax_amount' => $onboarding->tax_amount, 'total_amount' => $onboarding->total,
                'currency' => $onboarding->currency, 'purchase_snapshot' => $snapshot,
                'payment_provider' => $attempt->provider, 'payment_reference' => $attempt->provider_payment_id,
                'paid_at' => $onboarding->paid_at, 'activated_at' => now(),
            ],
        );
    }

    private function subscription($onboarding, Tenant $tenant, Plan $plan, array $snapshot): Subscription
    {
        $subscription = Subscription::where('tenant_id', $tenant->id)
            ->whereIn('status', Subscription::CURRENT_STATUSES)->first();
        if ($subscription && $subscription->plan_id !== $plan->id) {
            throw new OnboardingProvisioningException('SUBSCRIPTION_CONFLICT');
        }
        if (!$subscription) {
            $start = now();
            $months = $onboarding->billing_period === 'annual' ? 12 : 1;
            $subscription = Subscription::create([
                'tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'status' => 'active',
                'operations_limit' => $snapshot['plan']['included_operations'] ?? null,
                'started_at' => $start, 'current_period_start' => $start,
                'current_period_end' => $start->copy()->addMonthsNoOverflow($months),
            ]);
            $subscription->events()->create([
                'tenant_id' => $tenant->id, 'actor_user_id' => $onboarding->owner_user_id,
                'event' => 'created', 'to_status' => 'active',
                'metadata' => ['source' => 'saas_onboarding', 'onboarding_uuid' => $onboarding->uuid],
            ]);
        }
        $this->audit($onboarding, 'SUBSCRIPTION_CREATED', ['subscription_uuid' => $subscription->uuid]);
        return $subscription;
    }

    private function entitlements($onboarding, Tenant $tenant, Subscription $subscription, array $snapshot, TenantSaasOrder $order): void
    {
        foreach ($snapshot['modules'] ?? [] as $line) {
            $module = Module::find($line['id'] ?? 0);
            if (!$module || $module->code !== $line['code']) {
                throw new OnboardingProvisioningException('SNAPSHOT_MODULE_MISSING');
            }
            Entitlement::updateOrCreate(
                ['subscription_id' => $subscription->id, 'module_id' => $module->id],
                [
                    'tenant_id' => $tenant->id, 'code' => $line['code'], 'is_enabled' => true,
                    'limit_value' => $line['limit_value'] ?? null,
                    'source' => !empty($line['included']) ? 'plan' : 'override',
                ],
            );
            if ($module->code === 'API' && Schema::hasTable('tenant_api_quota_policies')) {
                $monthly = (int) ($line['api_monthly_request_limit'] ?? 0);
                $rate = (int) ($line['api_rate_limit_per_minute'] ?? 0);
                if ($monthly > 0 && $rate > 0) {
                    TenantApiQuotaPolicy::firstOrCreate(
                        ['tenant_id' => $tenant->id, 'source_saas_order_id' => $order->id],
                        [
                            'monthly_request_limit' => $monthly, 'rate_limit_per_minute' => $rate,
                            'valid_from' => now(), 'valid_until' => $subscription->current_period_end,
                        ],
                    );
                }
            }
        }
        $this->audit($onboarding, 'ENTITLEMENTS_CREATED', ['count' => count($snapshot['modules'] ?? [])]);
    }

    private function allowance($onboarding, Tenant $tenant, Subscription $subscription, array $snapshot, TenantSaasOrder $order): void
    {
        $operations = (int) ($snapshot['requested_operations'] ?? 0);
        if ($operations < 1) return;
        TenantOperationAllowance::firstOrCreate(
            ['saas_order_id' => $order->id],
            [
                'tenant_id' => $tenant->id, 'subscription_id' => $subscription->id,
                'operations' => $operations, 'starts_at' => now(),
                'expires_at' => $subscription->current_period_end,
            ],
        );
        $this->audit($onboarding, 'ALLOWANCE_CREATED', ['operations' => $operations]);
    }

    private function domain($onboarding, Tenant $tenant): TenantDomain
    {
        if (!config('zigo_onboarding.managed_subdomains_are_verified')) {
            throw new OnboardingProvisioningException('MANAGED_DOMAIN_NOT_VERIFIABLE');
        }
        $hostname = $this->subdomains->hostname($onboarding->requested_subdomain);
        $conflict = TenantDomain::where('domain', $hostname)->where('tenant_id', '!=', $tenant->id)->exists();
        if ($conflict) throw new OnboardingProvisioningException('DOMAIN_CONFLICT');
        $domain = $tenant->domains()->updateOrCreate(
            ['domain' => $hostname],
            [
                'type' => 'subdomain', 'environment' => 'production', 'is_primary' => true,
                'status' => 'verified', 'verified_at' => now(),
            ],
        );
        $this->audit($onboarding, 'DOMAIN_CREATED', ['hostname' => $hostname]);
        return $domain;
    }

    private function assertInvariants($onboarding, Tenant $tenant, User $owner, Plan $plan, Subscription $subscription, array $snapshot, TenantDomain $domain): void
    {
        $expectedCodes = collect($snapshot['modules'] ?? [])->pluck('code')->sort()->values()->all();
        $actualCodes = $subscription->entitlements()->where('is_enabled', true)->pluck('code')->sort()->values()->all();
        $hostname = $this->subdomains->hostname($onboarding->requested_subdomain);
        $valid = $onboarding->tenant_id === $tenant->id
            && $onboarding->owner_user_id === $owner->id
            && $tenant->current_plan_id === $plan->id
            && $tenant->memberships()->where('user_id', $owner->id)->where('role', 'owner')->where('status', 'active')->exists()
            && $subscription->status === 'active' && $subscription->plan_id === $plan->id
            && $expectedCodes === $actualCodes && $tenant->branding()->exists()
            && $domain->is_primary && $domain->status === 'verified' && $domain->domain === $hostname;
        if (!$valid) throw new OnboardingProvisioningException('ACTIVE_INVARIANTS_FAILED');
    }

    private function audit(SaasOnboardingApplication $onboarding, string $event, array $metadata = []): void
    {
        $correlation = $this->correlation($onboarding, strtolower($event));
        if ($onboarding->events()->where('event', $event)->where('correlation_key', $correlation)->exists()) return;
        $onboarding->events()->create([
            'from_status' => $onboarding->status, 'to_status' => $onboarding->status,
            'event' => $event, 'actor_type' => 'system', 'correlation_key' => $correlation,
            'metadata_json' => $metadata ? $this->sanitizer->sanitize($metadata) : null,
            'created_at' => now(),
        ]);
    }

    private function correlation(SaasOnboardingApplication $onboarding, string $step): string
    {
        return 'provisioning:'.$onboarding->uuid.':'.$step;
    }
}
