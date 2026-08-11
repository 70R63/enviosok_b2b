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
        abort_unless($application->owner_is_new && !$application->owner_activation_completed_at, 410);
        abort_unless(Password::broker()->tokenExists($application->owner, $token), 410);
        return view('tenant.admin.auth.activate', [
            'tenant' => $context->tenant()->load('branding'),
            'applicationToken' => $applicationToken, 'token' => $token,
        ]);
    }

    public function store(Request $request, string $applicationToken, TenantContext $context)
    {
        $application = $this->application($applicationToken, $context);
        abort_unless($application->owner_is_new && !$application->owner_activation_completed_at, 410);
        $data = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);
        $owner = $application->owner;
        $status = Password::broker()->reset(
            ['email' => $owner->email, 'password' => $data['password'],
                'password_confirmation' => $request->input('password_confirmation'), 'token' => $data['token']],
            function ($user, string $password) use ($owner): void {
                abort_unless((int) $user->id === (int) $owner->id, 403);
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            },
        );
        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['password' => __($status)]);
        }

        $application = DB::transaction(function () use ($application, $owner): SaasOnboardingApplication {
            $locked = SaasOnboardingApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
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
}
