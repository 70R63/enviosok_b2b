<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Network\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash, Password};
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

final class TenantPasswordResetController extends Controller
{
    public function create(TenantContext $context)
    {
        return view('tenant.admin.auth.forgot-password', ['tenant' => $context->tenant()?->load('branding')]);
    }

    public function store(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        try {
            Password::broker()->sendResetLink($request->only('email'));
        } catch (\Throwable) {
            // Keep the response indistinguishable for unknown accounts.
        }
        return back()->with('status', 'Si existe una cuenta asociada, enviaremos instrucciones de acceso.');
    }

    public function reset(Request $request, TenantContext $context, string $token)
    {
        return view('tenant.admin.auth.reset-password', [
            'tenant' => $context->tenant()?->load('branding'), 'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'], 'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);
        $status = Password::broker()->reset($data, function ($user) use ($data): void {
            $user->forceFill(['password' => Hash::make($data['password']), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });
        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('tenant.admin.login')->with('status', __('passwords.reset'));
        }
        return back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }
}
