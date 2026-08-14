<?php

namespace App\Domain\Network\Billing;

use App\Domain\Network\Billing\Models\{Entitlement, Subscription};
use App\Domain\Network\Catalog\Models\{Module, Plan};
use App\Domain\Network\Tenancy\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EntitlementNormalizationService
{
    public const CANONICAL_CODES = ['B2C', 'SHIPPING', 'TRACKING', 'DRIVER'];
    public const LEGACY_PORTAL_CODES = ['WHITE_LABEL', 'CUSTOMERS'];

    public function run(bool $apply = false): array
    {
        $execute = fn (): array => $this->normalize($apply);

        return $apply ? DB::transaction($execute) : $execute();
    }

    private function normalize(bool $apply): array
    {
        $rows = [];
        $b2c = Module::where('code', 'B2C')->first();
        $plans = Plan::with(['modules' => fn ($query) => $query->wherePivot('is_included', true)])->orderBy('id')->get();

        foreach ($plans as $plan) {
            $codes = $plan->modules->pluck('code');
            $legacyEligible = $codes->intersect(self::LEGACY_PORTAL_CODES)->isNotEmpty();
            if ($legacyEligible && ! $codes->contains('B2C')) {
                if (! $b2c && $apply) {
                    $b2c = Module::firstOrCreate(['code' => 'B2C'], [
                        'name' => 'Portal y clientes', 'description' => 'Landing tenant, acceso y Customer App.',
                        'type' => 'channel', 'is_active' => true, 'sort_order' => 15,
                    ]);
                }
                $rows[] = $this->row('PLAN', null, $plan, null, 'B2C', $apply ? 'ADDED' : 'WOULD_ADD');
                if ($apply && $b2c) {
                    $plan->modules()->syncWithoutDetaching([$b2c->id => ['is_included' => true, 'limit_value' => null]]);
                }
            }
        }

        $canonical = Module::whereIn('code', self::CANONICAL_CODES)->get()->keyBy('code');
        foreach (Tenant::with('currentPlan')->orderBy('id')->get() as $tenant) {
            $subscription = $this->currentSubscription($tenant);
            if (! $subscription) {
                $rows[] = $this->row('TENANT', $tenant, $tenant->currentPlan, null, '—', 'NO_CURRENT_SUBSCRIPTION');
                continue;
            }
            $plan = Plan::with(['modules' => fn ($query) => $query->wherePivot('is_included', true)])->find($subscription->plan_id);
            if (! $plan) {
                $rows[] = $this->row('TENANT', $tenant, null, $subscription, '—', 'PLAN_NOT_FOUND');
                continue;
            }
            $planCodes = $plan->modules->pluck('code');
            if ($planCodes->intersect(self::LEGACY_PORTAL_CODES)->isNotEmpty() && ! $planCodes->contains('B2C')) {
                $planCodes->push('B2C');
            }
            $existing = $subscription->entitlements()->pluck('code');
            foreach (self::CANONICAL_CODES as $code) {
                if (! $planCodes->contains($code) || $existing->contains($code)) continue;
                $module = $canonical->get($code) ?? ($code === 'B2C' ? $b2c : null);
                if (! $module) {
                    if (! $apply && $code === 'B2C') {
                        $rows[] = $this->row('SNAPSHOT', $tenant, $plan, $subscription, $code, 'WOULD_ADD');
                        continue;
                    }
                    $rows[] = $this->row('SNAPSHOT', $tenant, $plan, $subscription, $code, 'MODULE_NOT_FOUND');
                    continue;
                }
                $rows[] = $this->row('SNAPSHOT', $tenant, $plan, $subscription, $code, $apply ? 'ADDED' : 'WOULD_ADD');
                if ($apply) {
                    Entitlement::firstOrCreate(
                        ['subscription_id' => $subscription->id, 'code' => $code],
                        ['tenant_id' => $tenant->id, 'module_id' => $module->id, 'is_enabled' => true, 'limit_value' => $plan->modules->firstWhere('code', $code)?->pivot?->limit_value, 'source' => 'plan'],
                    );
                }
            }
        }

        return $rows;
    }

    private function currentSubscription(Tenant $tenant): ?Subscription
    {
        return Subscription::where('tenant_id', $tenant->id)->whereIn('status', Subscription::CURRENT_STATUSES)
            ->where('started_at', '<=', now())->where('current_period_end', '>=', now())->latest('started_at')->first();
    }

    private function row(string $scope, ?Tenant $tenant, ?Plan $plan, ?Subscription $subscription, string $missing, string $action): array
    {
        return [
            'scope' => $scope, 'tenant' => $tenant ? $tenant->name.' (#'.$tenant->id.')' : '—',
            'plan' => $plan ? $plan->name.' (#'.$plan->id.')' : '—',
            'subscription' => $subscription ? $subscription->uuid.' (#'.$subscription->id.')' : '—',
            'missing' => $missing, 'action' => $action,
        ];
    }
}
