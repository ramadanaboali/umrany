<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Modules\Core\Models\Admin;

/**
 * Uses Laravel's built-in password-broker (Password facade) against the `admins` broker
 * (config/auth.php) — admins always have an email, so the native email-link flow fits cleanly
 * here, unlike end users where a mobile-only account needs the custom OTP mechanism instead
 * (see Modules\Core\Actions\Auth\RequestPasswordReset / ResetPassword).
 */
final class PasswordResetController extends Controller
{
    public function createForgot(): View
    {
        return view('admin.auth.forgot-password');
    }

    public function storeForgot(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::broker('admins')->sendResetLink($request->only('email'));

        // Same message whether or not the email matches an admin account — no enumeration.
        return back()->with('status', 'If that account exists, a password reset link has been sent.');
    }

    public function createReset(Request $request, string $token): View
    {
        return view('admin.auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function storeReset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
        ]);

        $status = Password::broker('admins')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Admin $admin, string $password): void {
                $admin->forceFill(['password' => $password])->save();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => [__($status)]]);
        }

        return redirect()->route('admin.login')->with('status', 'Password has been reset — please sign in.');
    }
}
