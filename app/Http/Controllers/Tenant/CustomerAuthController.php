<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Network\Channels\B2C\Models\TenantCustomerProfile;
use App\Domain\Network\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use App\Domain\Network\Onboarding\Models\SaasOnboardingApplication;
use Illuminate\Support\Facades\Schema;

final class CustomerAuthController extends Controller
{
    public function create(TenantContext $context)
    {
        if (auth()->check() && $this->profile($context)) return redirect('/app');
        return view('tenant.customer.auth.login', ['tenant' => $context->tenant()->load('branding')]);
    }

    public function store(Request $request, TenantContext $context)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }
        if (! $this->profile($context)) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }
        $request->session()->regenerate();
        $this->claimPendingQuote($request, $context);
        return redirect()->intended('/app');
    }

    public function registration(TenantContext $context)
    {
        return view('tenant.customer.auth.register', ['tenant' => $context->tenant()->load('branding')]);
    }

    public function register(Request $request, TenantContext $context)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()], 'terms' => ['accepted'],
        ]);
        [$user, $profile, $created] = DB::transaction(function () use ($data, $context): array {
            $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower($data['email'])])->lockForUpdate()->first();
            $created = false;
            if (! $user) {
                $user = User::create([
                    'name' => $data['name'], 'email' => mb_strtolower($data['email']),
                    'password' => Hash::make($data['password']),
                    'empresa_id' => $this->legacyEmpresaId($context),
                ]);
                $created = true;
            } elseif (! Hash::check($data['password'], $user->password)) {
                throw ValidationException::withMessages(['email' => 'No fue posible crear la cuenta con estos datos.']);
            }
            $profile = TenantCustomerProfile::firstOrCreate(
                ['tenant_id' => $context->id(), 'user_id' => $user->id],
                ['status' => 'active', 'display_name' => $data['name']]
            );
            if ($profile->status !== 'active') throw ValidationException::withMessages(['email' => 'No fue posible crear la cuenta con estos datos.']);
            return [$user, $profile, $created];
        });
        if ($created) event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();
        $request->attributes->set('customer_profile', $profile);
        $claimed = $this->claimPendingQuote($request, $context, $profile);
        return redirect($claimed ? '/app/envio/nuevo' : '/app');
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }

    private function profile(TenantContext $context): ?TenantCustomerProfile
    {
        if (! auth()->check()) return null;
        return TenantCustomerProfile::where('tenant_id', $context->id())->where('user_id', auth()->id())->where('status', 'active')->first();
    }

    private function claimPendingQuote(Request $request, TenantContext $context, ?TenantCustomerProfile $profile = null): bool
    {
        $pending = $request->session()->pull('tenant_customer.pending_quote');
        if (! is_array($pending) || (int) ($pending['tenant_id'] ?? 0) !== $context->id()) return false;
        $profile ??= $this->profile($context);
        if (! $profile) return false;
        $updated = \App\Domain\Network\Channels\B2C\Models\TenantOperation::where('tenant_id', $context->id())
            ->where('uuid', (string) ($pending['operation_uuid'] ?? ''))->whereNull('customer_profile_id')
            ->update(['customer_profile_id' => $profile->id, 'created_by_user_id' => auth()->id()]);
        if ($updated || \App\Domain\Network\Channels\B2C\Models\TenantOperation::where('tenant_id', $context->id())->where('uuid', (string) ($pending['operation_uuid'] ?? ''))->where('customer_profile_id', $profile->id)->exists()) {
            $request->session()->put('tenant_customer.active_operation', (string) $pending['operation_uuid']);
            return true;
        }
        return false;
    }

    private function legacyEmpresaId(TenantContext $context): int
    {
        $empresaId = Schema::hasTable('saas_onboarding_applications')
            ? SaasOnboardingApplication::where('tenant_id', $context->id())
                ->where('status', SaasOnboardingApplication::ACTIVE)->value('legacy_empresa_id')
            : null;
        $empresaId ??= $context->tenant()->memberships()->where('role', 'owner')->where('status', 'active')
            ->join('users', 'users.id', '=', 'network_tenant_memberships.user_id')
            ->value('users.empresa_id');
        abort_unless($empresaId, 409, 'El tenant no tiene empresa legacy vinculada.');
        return (int) $empresaId;
    }
}
