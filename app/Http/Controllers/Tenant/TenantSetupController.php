<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Network\Onboarding\Models\SaasOnboardingApplication;
use App\Domain\Network\Tenancy\{TenantAccessService, TenantContext};
use App\Domain\Payments\Models\TenantPaymentConnection;
use App\Domain\AI\Usage\AiCapacityService;
use App\Domain\Network\ProductShell\{TenantWorkspace, TenantWorkspaceResolver};
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class TenantSetupController extends Controller
{
    public function show(Request $request, TenantContext $context, TenantAccessService $access, TenantWorkspaceResolver $workspaces, AiCapacityService $capacities)
    {
        abort_unless($access->role($request->user()) === 'owner', 403);
        $application = $this->application($context, $request->user()->id);
        if (!$application->setup_started_at) {
            DB::transaction(function () use ($application, $request): void {
                $locked = SaasOnboardingApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
                if (!$locked->setup_started_at) {
                    $locked->update(['setup_started_at' => now()]);
                    $this->event($locked, 'SETUP_STARTED', $request->user()->id);
                }
            });
        }
        $tenant = $context->tenant()->load(['branding', 'domains', 'currentPlan']);
        $subscription = $tenant->subscriptions()->where('status', 'active')->with('entitlements.module')->first();
        return view('tenant.admin.setup', [
            'tenant' => $tenant, 'application' => $application->fresh(), 'subscription' => $subscription,
            'isAiWorkspace' => $workspaces->resolveForPresentation($tenant) === TenantWorkspace::ZigoAi,
            'aiCapacity' => $capacities->summary($tenant),
            'connection' => TenantPaymentConnection::where('tenant_id', $tenant->id)
                ->where('provider', 'MERCADO_PAGO')->first(),
        ]);
    }

    public function store(Request $request, TenantContext $context, TenantAccessService $access)
    {
        abort_unless($access->role($request->user()) === 'owner', 403);
        $application = $this->application($context, $request->user()->id);
        $data = $request->validate([
            'brand_name' => ['required', 'string', 'max:160'],
            'primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'finish' => ['nullable', 'boolean'],
        ]);
        $branding = $context->tenant()->branding()->firstOrCreate([]);
        $brandingData = collect($data)->only(['brand_name', 'primary_color', 'secondary_color', 'accent_color'])
            ->filter(fn ($value) => $value !== null)->all();
        if ($request->hasFile('logo')) {
            $brandingData['logo_path'] = $request->file('logo')->store('tenant-branding/'.$context->tenant()->uuid, 'public');
        }
        $branding->update($brandingData);

        if ($request->boolean('finish')) {
            DB::transaction(function () use ($application, $request): void {
                $locked = SaasOnboardingApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
                if (!$locked->setup_completed_at) {
                    $locked->update(['setup_started_at' => $locked->setup_started_at ?? now(), 'setup_completed_at' => now()]);
                    $this->event($locked, 'SETUP_COMPLETED', $request->user()->id);
                }
            });
            return redirect()->route('tenant.admin.dashboard')->with('success', 'La configuración inicial quedó completada.');
        }
        return back()->with('success', 'La configuración fue guardada. Puedes finalizar cuando estés listo.');
    }

    private function application(TenantContext $context, int $ownerId): SaasOnboardingApplication
    {
        return SaasOnboardingApplication::where('tenant_id', $context->id())
            ->where('owner_user_id', $ownerId)->where('status', SaasOnboardingApplication::ACTIVE)
            ->firstOrFail();
    }

    private function event(SaasOnboardingApplication $application, string $event, int $actorId): void
    {
        $application->events()->firstOrCreate(
            ['event' => $event, 'correlation_key' => strtolower($event).':'.$application->uuid],
            [
                'from_status' => $application->status, 'to_status' => $application->status,
                'actor_type' => 'owner', 'actor_id' => $actorId,
                'metadata_json' => null, 'created_at' => now(),
            ],
        );
    }
}
