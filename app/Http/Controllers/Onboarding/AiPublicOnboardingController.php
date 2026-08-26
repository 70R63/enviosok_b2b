<?php

namespace App\Http\Controllers\Onboarding;

use App\Domain\Network\Catalog\Models\{Module, Plan};
use App\Domain\Network\Commerce\Models\NetworkCommercialProduct;
use App\Domain\Network\Commerce\PlatformPaymentService;
use App\Domain\Network\Onboarding\Models\SaasOnboardingApplication;
use App\Domain\Network\Onboarding\Services\{
    OnboardingApplicationService,
    OnboardingMetadataSanitizer,
    OnboardingStateService,
    OnboardingSubdomainService
};
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\StartAiOnboardingRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\{Log, Schema};
use Throwable;

final class AiPublicOnboardingController extends Controller
{
    public function landing()
    {
        $trial=$this->trialProduct();
        return view('agentes-ia.landing', ['product' => $trial ?? $this->baseProduct(), 'trial' => $trial]);
    }

    public function pricing()
    {
        $products = NetworkCommercialProduct::query()->where('is_active', true)->whereIn('type', ['PLAN','ADDON'])->whereJsonContains('metadata->ai_product', true)->whereJsonContains('metadata->ai_kind', 'base')->whereIn('billing_type', ['MONTHLY','ANNUAL'])->when(Schema::hasColumn('network_commercial_products','is_public'), fn($q)=>$q->where('is_public',true))->orderBy('sort_order')->get();
        return view('agentes-ia.pricing', ['product' => $products->first(), 'products' => $products]);
    }

    public function start(Request $request)
    {
        $offer=(string)$request->query('offer');
        if ($offer==='') $offer=(string)optional($this->trialProduct())->uuid;
        return view('agentes-ia.start', ['product' => $this->product($offer)]);
    }

    public function storeStart(
        StartAiOnboardingRequest $request,
        OnboardingApplicationService $applications,
        OnboardingSubdomainService $subdomains,
        OnboardingStateService $states,
        OnboardingMetadataSanitizer $sanitizer,
        \App\Domain\Network\Onboarding\Services\SaasTenantProvisioningService $provisioning,
    ) {
        $data = $request->validated();
        $product = $this->product((string) ($data['offer'] ?? ''));
        $plan = $this->ensureAiPlan($product);
        $purchaseKey = $this->purchaseKey($request);
        $isTrial = (bool) data_get($product->metadata, 'trial', false);
        if ($isTrial) $purchaseKey = 'ai-trial:'.$purchaseKey;
        unset($data['offer'], $data['requested_subdomain']);

        $application = $applications->createOrRecover($data + [
            'selected_plan_id' => $plan->id,
            'billing_period' => $product->billing_type === 'ANNUAL' ? 'annual' : 'monthly',
        ], $purchaseKey);
        if ($application->commercial_snapshot_json === null) {
            $applications->updateDraft($application, [
                'selected_plan_id' => $plan->id,
                'billing_period' => $product->billing_type === 'ANNUAL' ? 'annual' : 'monthly',
                'selected_modules_json' => [
                    'plan_offer_uuid' => $product->uuid,
                    'module_ids' => [],
                    'module_offer_uuids' => [],
                    'operations_offer_uuid' => null,
                    'ai_product_uuid' => $product->uuid,
                ],
                'requested_operations' => null,
            ], 'AI_PRODUCT_SELECTED', ['commercial_code' => $product->code]);
            $application = $subdomains->reserve($application, $request->validated('requested_subdomain'));
            $selection = $application->selected_modules_json ?? [];
            $application = $applications->freezeCommercialSnapshot(
                $application, [], null, (string) config('zigo_onboarding.tax_rate', '0.00'),
                $selection['plan_offer_uuid'], null,
            );
            $application = $states->transition($application, $isTrial ? SaasOnboardingApplication::TRIAL_READY : SaasOnboardingApplication::PENDING_PAYMENT, $isTrial ? 'AI_TRIAL_READY' : 'AI_READY_FOR_CHECKOUT', 'public_session');
            $application->events()->firstOrCreate(
                ['event' => 'AI_CONTACT_CONTEXT_CAPTURED'],
                ['from_status' => $application->status, 'to_status' => $application->status,
                    'actor_type' => 'public_session', 'metadata_json' => $sanitizer->sanitize([
                        'existing_user_record' => User::where('email', $application->contact_email)->exists(),
                    ]), 'created_at' => now()],
            );
            if ($isTrial) {
                $application = $provisioning->provision($application);
                return redirect()->route('agentes-ia.onboarding.summary', $application->public_token);
            }
        }

        return redirect()->route('agentes-ia.onboarding.summary', $application->public_token);
    }

    public function summary(string $token)
    {
        $application = $this->application($token);
        abort_unless(in_array($application->status, [
            SaasOnboardingApplication::PENDING_PAYMENT, SaasOnboardingApplication::PAID,
            SaasOnboardingApplication::PROVISIONING, SaasOnboardingApplication::ACTIVE,
        ], true), 409);
        return view('agentes-ia.summary', ['application' => $application]);
    }

    public function checkout(string $token, PlatformPaymentService $payments)
    {
        $application = $this->application($token);
        try {
            $attempt = $payments->initializeOnboarding($application);
        } catch (Throwable $exception) {
            Log::warning('AI public onboarding checkout initialization failed', [
                'onboarding_uuid' => $application->uuid,
                'reason' => 'PAYMENT_PROVIDER_ERROR', 'exception' => $exception::class,
            ]);
            return back()->withErrors(['payment' => 'No fue posible iniciar el pago en este momento.']);
        }
        $url = $attempt->getRawOriginal('init_point');
        $host = (string) parse_url($url, PHP_URL_HOST);
        abort_unless(parse_url($url, PHP_URL_SCHEME) === 'https'
            && preg_match('/(^|\.)mercadopago\.com(\.mx)?$/i', $host), 502);
        return redirect()->away($url);
    }

    public function returned(string $token, string $result)
    {
        abort_unless(in_array($result, ['success', 'pending', 'failure'], true), 404);
        return view('agentes-ia.return', ['application' => $this->application($token)]);
    }

    private function baseProduct(): NetworkCommercialProduct
    {
        return $this->product('');
    }

    private function trialProduct(): ?NetworkCommercialProduct
    {
        return NetworkCommercialProduct::query()->where('is_active', true)->whereJsonContains('metadata->ai_product', true)->whereJsonContains('metadata->trial', true)->when(Schema::hasColumn('network_commercial_products','is_public'), fn($q)=>$q->where('is_public',true))->first();
    }

    private function product(string $uuid): NetworkCommercialProduct
    {
        $query = NetworkCommercialProduct::query()->where('is_active', true)
            ->whereIn('type', ['PLAN','ADDON'])->whereJsonContains('metadata->ai_product', true)
            ->whereJsonContains('metadata->ai_kind', 'base')
            ->whereIn('billing_type', ['MONTHLY', 'ANNUAL'])
            ->where(function($q) use ($uuid){$q->where('price','>',0); if ($uuid !== '') $q->orWhereJsonContains('metadata->trial',true);})
            ->when(Schema::hasColumn('network_commercial_products', 'is_public'), fn ($q) => $q->where('is_public', true));
        if (Str::isUuid($uuid)) $query->where('uuid', $uuid);
        return $query->orderBy('sort_order')->firstOrFail();
    }

    private function ensureAiPlan(NetworkCommercialProduct $product): Plan
    {
        $module = Module::query()->where('code', 'AI_CORE')->where('is_active', true)->firstOrFail();
        $planCode = (string) data_get($product->metadata, 'ai_plan_code', 'AI_INICIAL');
        $plan = Plan::query()->firstOrCreate(
            ['code' => $planCode],
            ['name' => 'Agentes IA', 'status' => 'active', 'monthly_price' => $product->billing_type === 'MONTHLY' ? $product->price : 0, 'annual_price' => $product->billing_type === 'ANNUAL' ? $product->price : null, 'currency' => $product->currency],
        );
        $plan->modules()->syncWithoutDetaching([$module->id => ['is_included' => true, 'limit_value' => null]]);
        if ((int) $product->plan_id !== (int) $plan->id) $product->update(['plan_id' => $plan->id]);
        return $plan;
    }

    private function application(string $token): SaasOnboardingApplication
    {
        abort_unless(strlen($token) === 64 && ctype_alnum($token), 404);
        return SaasOnboardingApplication::where('public_token', $token)->firstOrFail();
    }

    private function purchaseKey(Request $request): string
    {
        $key = (string) $request->session()->get('ai_onboarding_purchase_key');
        if (! Str::isUuid($key)) {
            $key = (string) Str::uuid();
            $request->session()->put('ai_onboarding_purchase_key', $key);
        }
        return $key;
    }
}
