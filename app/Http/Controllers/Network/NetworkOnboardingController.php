<?php

namespace App\Http\Controllers\Network;

use App\Domain\Network\Commerce\Models\PlatformPaymentAttempt;
use App\Domain\Network\Onboarding\Models\SaasOnboardingApplication;
use App\Domain\Network\Onboarding\Services\SaasTenantProvisioningService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class NetworkOnboardingController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(SaasOnboardingApplication::STATUSES)]]);
        $applications = SaasOnboardingApplication::query()
            ->with(['plan:id,name', 'tenant:id,uuid,name'])
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->withMax('events as last_event_at', 'created_at')->latest()->paginate(40)->withQueryString();
        $attempts = PlatformPaymentAttempt::whereIn('onboarding_application_id', $applications->pluck('id'))
            ->latest('id')->get()->unique('onboarding_application_id')->keyBy('onboarding_application_id');
        return view('network.onboarding.index', compact('applications', 'attempts'));
    }

    public function show(string $uuid)
    {
        $application = SaasOnboardingApplication::where('uuid', $uuid)->with([
            'plan', 'events' => fn ($query) => $query->orderBy('id'),
            'tenant.branding', 'tenant.domains', 'tenant.subscriptions.entitlements.module', 'owner:id,name,email,empresa_id',
        ])->firstOrFail();
        $payment = PlatformPaymentAttempt::where('onboarding_application_id', $application->id)->latest()->first();
        return view('network.onboarding.show', compact('application', 'payment'));
    }

    public function retry(Request $request, string $uuid, SaasTenantProvisioningService $provisioning)
    {
        $application = SaasOnboardingApplication::where('uuid', $uuid)->firstOrFail();
        abort_unless(in_array($application->status, [
            SaasOnboardingApplication::PAID, SaasOnboardingApplication::FAILED,
        ], true), 409);
        $application->events()->create([
            'from_status' => $application->status, 'to_status' => $application->status,
            'event' => 'PROVISIONING_RETRY_REQUESTED', 'actor_type' => 'network_user',
            'actor_id' => $request->user()->id,
            'correlation_key' => 'network-retry:'.$application->uuid.':'.now()->format('YmdHisv'),
            'metadata_json' => null, 'created_at' => now(),
        ]);
        $result = $provisioning->provision($application);
        return redirect()->route('network.onboarding.show', $result->uuid)
            ->with('success', 'Retry ejecutado. Estado actual: '.$result->status.'.');
    }
}
