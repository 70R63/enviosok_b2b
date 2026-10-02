<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Network\Onboarding\Models\SaasOnboardingApplication;
use App\Domain\Network\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Hash, Password};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

final class OwnerActivationController extends Controller
{
    public function create(string $applicationToken, string $token, TenantContext $context)
    {
        $application = $this->application($applicationToken, $context);
        if (!$application->owner_is_new || $application->owner_activation_completed_at ||
            !$application->owner || !Password::broker('owner_activations')->tokenExists($application->owner, $token)) {
            return $this->unavailable($context);
        }
        return view('tenant.admin.auth.activate', [
            'tenant' => $context->tenant()->load('branding'),
            'applicationToken' => $applicationToken, 'token' => $token,
        ]);
    }

    public function store(Request $request, string $applicationToken, TenantContext $context)
    {
        $application = $this->application($applicationToken, $context);
        if (!$application->owner_is_new || $application->owner_activation_completed_at || !$application->owner ||
            !Password::broker('owner_activations')->tokenExists($application->owner, (string) $request->input('token'))) {
            return $this->unavailable($context);
        }
        $data = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);
        $owner = $application->owner;
        $application = DB::transaction(function () use ($application, $owner, $data, $request): ?SaasOnboardingApplication {
            $locked = SaasOnboardingApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
            if (!$locked->owner_is_new || $locked->owner_activation_completed_at ||
                $locked->status !== SaasOnboardingApplication::ACTIVE ||
                (int) $locked->owner_user_id !== (int) $owner->id) {
                return null;
            }
            $status = Password::broker('owner_activations')->reset(
                ['email' => $owner->email, 'password' => $data['password'],
                    'password_confirmation' => $request->input('password_confirmation'), 'token' => $data['token']],
                function ($user, string $password) use ($owner): void {
                    abort_unless((int) $user->id === (int) $owner->id, 403);
                    $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                    event(new PasswordReset($user));
                },
            );
            if ($status !== Password::PASSWORD_RESET) {
                return null;
            }
            if (!$locked->owner_activation_completed_at) {
                $locked->update(['owner_activation_completed_at' => now()]);
                $locked->events()->create([
                    'from_status' => $locked->status, 'to_status' => $locked->status,
                    'event' => 'OWNER_ACTIVATION_COMPLETED', 'actor_type' => 'owner',
                    'actor_id' => $owner->id, 'correlation_key' => 'owner-activation:'.$locked->uuid,
                    'metadata_json' => null, 'created_at' => now(),
                ]);
            }
            return $locked;
        });
        if (!$application) {
            return $this->unavailable($context);
        }
        Auth::login($owner);
        $request->session()->regenerate();
        return redirect()->route($application->setup_completed_at ? 'tenant.admin.dashboard' : 'tenant.admin.setup');
    }

    private function application(string $token, TenantContext $context): SaasOnboardingApplication
    {
        return SaasOnboardingApplication::with('owner')->where('public_token', $token)
            ->where('tenant_id', $context->id())->where('status', SaasOnboardingApplication::ACTIVE)
            ->firstOrFail();
    }

    private function unavailable(TenantContext $context)
    {
        return response()->view('tenant.admin.auth.activation-unavailable', [
            'adminUrl' => route('tenant.admin.dashboard'),
            'validity' => \App\Notifications\SaasOwnerWelcomeNotification::formatDuration(
                (int) config('auth.passwords.owner_activations.expire'),
            ),
        ], 410)->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
