<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Throwable;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(
        Request $request
    ): RedirectResponse {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );

            if (!in_array(
                $status,
                [
                    Password::RESET_LINK_SENT,
                    Password::INVALID_USER,
                    Password::RESET_THROTTLED,
                ],
                true
            )) {
                Log::warning(
                    'Password broker devolvió un estado inesperado.',
                    ['status' => $status]
                );
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return back()->with(
            'status',
            __('passwords.sent_generic')
        );
    }
}