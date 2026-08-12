<?php

namespace App\Http\Controllers\Onboarding;

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
use App\Http\Requests\Onboarding\{ReserveOnboardingSubdomainRequest, StartOnboardingRequest};
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\{Log, Schema};
use Illuminate\Validation\ValidationException;
use Throwable;

final class ZigoPlatformController extends Controller
{
    public function landing()
    {
        return view('zigo-platform.landing');
    }

    public function pricing()
    {
        return view('zigo-platform.pricing', ['offers' => $this->planOffers()]);
    }

    public function start(Request $request)
    {
        $offer = $this->findPublicPlanOffer((string) $request->query('offer'), false);
        return view('zigo-platform.start', ['offer' => $offer]);
    }

    public function storeStart(
        StartOnboardingRequest $request,
        OnboardingApplicationService $applications,
        OnboardingMetadataSanitizer $sanitizer,
    ) {
        $data = $request->validated();
        $offer = $this->findPublicPlanOffer((string) ($data['offer'] ?? ''), false);
        $purchaseKey = (string) $request->session()->get('zigo_onboarding_purchase_key');
        if (!Str::isUuid($purchaseKey)) {
            $purchaseKey = (string) Str::uuid();
            $request->session()->put('zigo_onboarding_purchase_key', $purchaseKey);
        }
        unset($data['offer']);
        $application = $applications->createOrRecover($data, $purchaseKey);

        $application->events()->firstOrCreate(
            ['event' => 'CONTACT_CONTEXT_CAPTURED'],
            [
                'from_status' => $application->status,
                'to_status' => $application->status,
                'actor_type' => 'public_session',
                'metadata_json' => $sanitizer->sanitize([
                    'existing_user_record' => User::where('email', $application->contact_email)->exists(),
                ]),
                'created_at' => now(),
            ],
        );
        if ($offer) {
            $applications->updateDraft($application, [
                'selected_plan_id' => $offer->plan_id,
                'billing_period' => $this->billingPeriod($offer),
                'selected_modules_json' => [
                    'plan_offer_uuid' => $offer->uuid,
                    'module_ids' => [],
                    'module_offer_uuids' => [],
                    'operations_offer_uuid' => null,
                ],
                'requested_operations' => null,
            ], 'PLAN_SELECTED', ['commercial_code' => $offer->code]);
        }

        return redirect()->route('zigo-platform.onboarding.solution', $application->public_token);
    }

    public function solution(Request $request, string $token)
    {
        $application = $this->application($token);
        abort_unless($application->status === SaasOnboardingApplication::DRAFT, 409);

        $offerUuid = $application->selected_modules_json['plan_offer_uuid'] ?? null;
        $offer = $this->findPublicPlanOffer((string) $offerUuid, false);
        if (!$offer && $this->planOffers()->isNotEmpty()) {
            return redirect()->route('zigo-platform.pricing');
        }

        return view('zigo-platform.solution', ['application' => $application, 'offer' => $offer]);
    }

    public function storeSolution(
        Request $request,
        string $token,
        OnboardingApplicationService $applications,
    ) {
        $application = $this->application($token);
        $selection = $application->selected_modules_json ?? [];
        $planOffer = $this->findPublicPlanOffer((string) ($selection['plan_offer_uuid'] ?? ''));

        $applications->updateDraft($application, [
            'selected_plan_id' => $planOffer->plan_id,
            'billing_period' => $this->billingPeriod($planOffer),
            'selected_modules_json' => [
                'plan_offer_uuid' => $planOffer->uuid,
                'module_ids' => [],
                'module_offer_uuids' => [],
                'operations_offer_uuid' => null,
            ],
            'requested_operations' => null,
        ], 'SOLUTION_SELECTED', ['plan_code' => $planOffer->plan->code]);

        return redirect()->route('zigo-platform.onboarding.platform', $application->public_token);
    }

    public function platform(string $token)
    {
        $application = $this->application($token);
        abort_unless($application->status === SaasOnboardingApplication::DRAFT && $application->selected_plan_id, 409);
        return view('zigo-platform.platform', [
            'application' => $application,
            'domainBase' => config('zigo_onboarding.subdomain_base'),
        ]);
    }

    public function storePlatform(
        ReserveOnboardingSubdomainRequest $request,
        string $token,
        OnboardingSubdomainService $subdomains,
        OnboardingApplicationService $applications,
        OnboardingStateService $states,
    ) {
        $application = $subdomains->reserve(
            $this->application($token),
            $request->validated('requested_subdomain'),
        );
        $selection = $application->selected_modules_json ?? [];
        $application = $applications->freezeCommercialSnapshot(
            $application,
            $selection['module_ids'] ?? [],
            $application->requested_operations,
            (string) config('zigo_onboarding.tax_rate', '0.00'),
            $selection['plan_offer_uuid'] ?? null,
            $selection['operations_offer_uuid'] ?? null,
        );
        $application = $states->transition(
            $application,
            SaasOnboardingApplication::PENDING_PAYMENT,
            'READY_FOR_CHECKOUT',
            'public_session',
        );

        return redirect()->route('zigo-platform.onboarding.summary', $application->public_token);
    }

    public function summary(string $token)
    {
        $application = $this->application($token);
        abort_unless(in_array($application->status, [
            SaasOnboardingApplication::PENDING_PAYMENT,
            SaasOnboardingApplication::PAID,
            SaasOnboardingApplication::PROVISIONING,
            SaasOnboardingApplication::ACTIVE,
        ], true), 409);
        return view('zigo-platform.summary', ['application' => $application]);
    }

    public function checkout(string $token, PlatformPaymentService $payments)
    {
        $application = $this->application($token);
        if (!config('zigo_payments.platform.enabled')) {
            Log::warning('ZIGO onboarding checkout unavailable', [
                'onboarding_uuid' => $application->uuid,
                'reason' => 'PLATFORM_PAYMENT_DISABLED',
            ]);
            return back()->withErrors(['payment' => 'No fue posible iniciar el pago en este momento. Intenta nuevamente.']);
        }
        try {
            $attempt = $payments->initializeOnboarding($application);
        } catch (Throwable $exception) {
            $reason = preg_match('/^(?:PLATFORM_PAYMENT_CONFIGURATION_MISSING|MERCADO_PAGO_PREFERENCE_HTTP_\d{3})$/', $exception->getMessage())
                ? $exception->getMessage()
                : 'PAYMENT_PROVIDER_ERROR';
            Log::warning('ZIGO onboarding checkout initialization failed', [
                'onboarding_uuid' => $application->uuid,
                'reason' => $reason,
                'exception' => $exception::class,
            ]);
            return back()->withErrors(['payment' => 'No fue posible iniciar el pago en este momento. Intenta nuevamente.']);
        }
        $url = $attempt->getRawOriginal('init_point');
        $host = (string) parse_url($url, PHP_URL_HOST);
        abort_unless(
            parse_url($url, PHP_URL_SCHEME) === 'https' &&
            preg_match('/(^|\.)mercadopago\.com(\.mx)?$/i', $host),
            502,
        );
        return redirect()->away($url);
    }

    public function returned(string $token, string $result)
    {
        abort_unless(in_array($result, ['success', 'pending', 'failure'], true), 404);
        return view('zigo-platform.return', ['application' => $this->application($token)]);
    }

    private function application(string $token): SaasOnboardingApplication
    {
        abort_unless(strlen($token) === 64 && ctype_alnum($token), 404);
        return SaasOnboardingApplication::where('public_token', $token)->firstOrFail();
    }

    private function planOffers()
    {
        return NetworkCommercialProduct::with(['plan.modules'])
            ->where('is_active', true)->where('type', 'PLAN')
            ->when(Schema::hasColumn('network_commercial_products','is_public'),fn($query)=>$query->where('is_public',true)->whereNull('archived_at'))
            ->where('code', 'not like', '%RAPIDGO%')->where('name', 'not like', '%RapidGo%')
            ->whereIn('billing_type', ['MONTHLY', 'ANNUAL'])->where('price', '>', 0)
            ->whereHas('plan', fn ($query) => $query->where('status', 'active'))
            ->orderBy('sort_order')->get();
    }

    private function findPublicPlanOffer(string $uuid, bool $required = true): ?NetworkCommercialProduct
    {
        if (!Str::isUuid($uuid)) {
            if ($required) throw ValidationException::withMessages(['plan_offer' => 'Selecciona una oferta válida.']);
            return null;
        }
        $offer = NetworkCommercialProduct::with('plan')->where('uuid', $uuid)
            ->where('is_active', true)->where('type', 'PLAN')->where('price', '>', 0)
            ->when(Schema::hasColumn('network_commercial_products','is_public'),fn($query)=>$query->where('is_public',true)->whereNull('archived_at'))
            ->where('code', 'not like', '%RAPIDGO%')->where('name', 'not like', '%RapidGo%')
            ->whereIn('billing_type', ['MONTHLY', 'ANNUAL'])
            ->whereHas('plan', fn ($query) => $query->where('status', 'active'))->first();
        if (!$offer && $required) throw ValidationException::withMessages(['plan_offer' => 'La oferta no está disponible.']);
        return $offer;
    }

    private function billingPeriod(NetworkCommercialProduct $offer): string
    {
        return $offer->billing_type === 'ANNUAL' ? 'annual' : 'monthly';
    }
}
