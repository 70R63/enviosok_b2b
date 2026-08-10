<?php

namespace App\Http\Controllers\Network;

use App\Domain\Network\Security\NetworkTwoFactorService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class NetworkTwoFactorController extends Controller
{
    public function enrollment(Request $request, NetworkTwoFactorService $twoFactor)
    {
        $user = $this->pendingUser($request);
        $configuration = $twoFactor->beginEnrollment($user);
        if ($configuration->enabled_at) return redirect()->route('network.two-factor.challenge');
        return view('network.auth.two-factor-enroll', [
            'qrDataUri' => $twoFactor->qrDataUri($user, $configuration),
            'secret' => $configuration->secret,
        ]);
    }

    public function confirmEnrollment(Request $request, NetworkTwoFactorService $twoFactor)
    {
        $data = $request->validate(['code' => ['required', 'string', 'regex:/^\d{6}$/']]);
        $user = $this->pendingUser($request);
        $codes = $twoFactor->enroll($user, $data['code'], $request);
        if ($codes === false) throw ValidationException::withMessages(['code' => 'El código no es válido o ya fue utilizado.']);
        $this->complete($request, $user);
        return view('network.auth.two-factor-recovery', ['codes' => $codes, 'continueUrl' => route('network.launchpad')]);
    }

    public function challenge(Request $request, NetworkTwoFactorService $twoFactor)
    {
        $user = $this->pendingUser($request);
        if (! $twoFactor->configuration($user)?->enabled_at) return redirect()->route('network.two-factor.enroll');
        return view('network.auth.two-factor-challenge');
    }

    public function verify(Request $request, NetworkTwoFactorService $twoFactor)
    {
        $data = $request->validate([
            'method' => ['required', 'in:totp,recovery'],
            'code' => ['required', 'string', 'max:32'],
        ]);
        $user = $this->pendingUser($request);
        $valid = $data['method'] === 'recovery'
            ? $twoFactor->consumeRecoveryCode($user, $data['code'], $request)
            : $twoFactor->challenge($user, $data['code'], $request);
        if (! $valid) throw ValidationException::withMessages(['code' => 'El código no es válido o ya fue utilizado.']);
        $this->complete($request, $user);
        return redirect()->intended(route('network.launchpad'));
    }

    public function regenerate(Request $request, NetworkTwoFactorService $twoFactor)
    {
        return view('network.auth.two-factor-recovery', [
            'codes' => $twoFactor->regenerateRecoveryCodes($request->user(), $request),
            'continueUrl' => route('network.launchpad'),
        ]);
    }

    public function reset(User $user, Request $request, NetworkTwoFactorService $twoFactor)
    {
        $twoFactor->reset($user, $request->user(), $request);
        return back()->with('status', '2FA restablecido. El usuario deberá enrolarse nuevamente.');
    }

    private function pendingUser(Request $request): User
    {
        $user = User::query()->find((int) $request->session()->get('network.2fa_pending_user_id'));
        abort_unless($user?->hasRol('sysadmin'), 403);
        return $user;
    }

    private function complete(Request $request, User $user): void
    {
        Auth::login($user, false);
        $request->session()->regenerate();
        $request->session()->forget('network.2fa_pending_user_id');
        $request->session()->put('network.2fa_user_id', $user->id);
        $request->session()->put('network.2fa_verified_at', now()->timestamp);
    }
}
