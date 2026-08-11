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
use App\Http\Requests\Onboarding\{
    ReserveOnboardingSubdomainRequest,
    SelectOnboardingSolutionRequest,
    StartOnboardingRequest
};
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
            $request->session()->put('zigo_onboarding_initial_offer', $offer->uuid);
        }

        return redirect()->route('zigo-platform.onboarding.solution', $application->public_token);
    }

    public function solution(Request $request, string $token)
    {
        $application = $this->application($token);
        abort_unless($application->status === SaasOnboardingApplication::DRAFT, 409);

        return view('zigo-platform.solution', [
            'application' => $application,
            'offers' => $this->planOffers(),
            'moduleOffers' => NetworkCommercialProduct::with('module')
                ->where('is_active', true)->whereIn('type', ['MODULE', 'ADDON'])
                ->where('code', 'not like', '%RAPIDGO%')->where('name', 'not like', '%RapidGo%')
                ->whereHas('module', fn ($query) => $query->where('is_active', true))
                ->orderBy('sort_order')->get(),
            'operationOffers' => NetworkCommercialProduct::where('is_active', true)
                ->where('type', 'OPERATION_PACK')->where('billing_type', 'ONE_TIME')
                ->where('code', 'not like', '%RAPIDGO%')->where('name', 'not like', '%RapidGo%')
                ->orderBy('sort_order')->get(),
            'initialOffer' => $request->session()->pull('zigo_onboarding_initial_offer'),
        ]);
    }

    public function storeSolution(
        SelectOnboardingSolutionRequest $request,
        string $token,
        OnboardingApplicationService $applications,
    ) {
        $application = $this->application($token);
        $data = $request->validated();
        $billingType = $data['billing_period'] === 'annual' ? 'ANNUAL' : 'MONTHLY';
        $planOffer = $this->findPublicPlanOffer($data['plan_offer']);
        if ($planOffer->billing_type !== $billingType) {
            throw ValidationException::withMessages(['plan_offer' => 'La oferta no corresponde al periodo seleccionado.']);
        }

        $moduleOffers = NetworkCommercialProduct::with('module')->whereIn('uuid', $data['module_offers'] ?? [])
            ->where('is_active', true)->whereIn('type', ['MODULE', 'ADDON'])
            ->where('code', 'not like', '%RAPIDGO%')->where('name', 'not like', '%RapidGo%')
            ->where('billing_type', $billingType)->get();
        if ($moduleOffers->count() !== count($data['module_offers'] ?? []) || $moduleOffers->contains(fn ($offer) => !$offer->module?->is_active)) {
            throw ValidationException::withMessages(['module_offers' => 'Uno o más módulos no están disponibles.']);
        }

        $operationOffer = null;
        if (!empty($data['operations_offer'])) {
            $operationOffer = NetworkCommercialProduct::where('uuid', $data['operations_offer'])
                ->where('is_active', true)->where('type', 'OPERATION_PACK')
                ->where('code', 'not like', '%RAPIDGO%')->where('name', 'not like', '%RapidGo%')
                ->where('billing_type', 'ONE_TIME')->first();
            if (!$operationOffer) {
                throw ValidationException::withMessages(['operations_offer' => 'El volumen no está disponible.']);
            }
        }

        $applications->updateDraft($application, [
            'selected_plan_id' => $planOffer->plan_id,
            'billing_period' => $data['billing_period'],
            'selected_modules_json' => [
                'plan_offer_uuid' => $planOffer->uuid,
                'module_ids' => $moduleOffers->pluck('module_id')->unique()->values()->all(),
                'module_offer_uuids' => $moduleOffers->pluck('uuid')->values()->all(),
                'operations_offer_uuid' => $operationOffer?->uuid,
            ],
            'requested_operations' => $operationOffer?->included_operations,
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
        abort_unless(config('zigo_payments.platform.enabled'), 503);
        $attempt = $payments->initializeOnboarding($this->application($token));
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
            ->where('code', 'not like', '%RAPIDGO%')->where('name', 'not like', '%RapidGo%')
            ->whereIn('billing_type', ['MONTHLY', 'ANNUAL'])
            ->whereHas('plan', fn ($query) => $query->where('status', 'active'))->first();
        if (!$offer && $required) throw ValidationException::withMessages(['plan_offer' => 'La oferta no está disponible.']);
        return $offer;
    }
}
