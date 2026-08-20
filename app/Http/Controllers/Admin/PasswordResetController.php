<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\ForgotPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Core\Models\Admin;
use Modules\Core\Services\Admin\AdminAuthService;

/**
 * Uses Laravel's built-in password-broker (Password facade) against the `admins` broker
 * (config/auth.php) — admins always have an email, so the native email-link flow fits cleanly
 * here, unlike end users where a mobile-only account needs the custom OTP mechanism instead
 * (see Modules\Core\Services\AuthService::requestPasswordReset()/resetPassword()).
 *
 * The forgot-password step here deliberately reveals account existence — see
 * docs/decisions/0011-admin-forgot-password-reveals-account-existence.md. The end-user API's
 * equivalent endpoint is untouched and stays enumeration-safe; this asymmetry is intentional.
 */
final class PasswordResetController extends Controller
{
    public function __construct(
        private readonly AdminAuthService $adminAuth,
    ) {}

    public function createForgot(): View
    {
        return view('admin.auth.forgot-password');
    }

    public function storeForgot(ForgotPasswordRequest $request): RedirectResponse
    {
        $status = Password::broker('admins')->sendResetLink($request->only('email'));

        return match ($status) {
            Password::RESET_LINK_SENT => back()->with('status', __('admin.flash.reset_link_sent', ['email' => $request->string('email')->value()])),
            Password::RESET_THROTTLED => back()->withErrors(['email' => [__('admin.flash.reset_throttled')]]),
            default => back()->withErrors(['email' => [__($status)]]),
        };
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
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        try {
            $status = Password::broker('admins')->reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function (Admin $admin, string $password): void {
                    // The broker has already verified the token+email pair by the time this runs —
                    // this codebase's equivalent of the end-user side's "only after OTP
                    // consumption" placement for the reuse check (Modules\Core\Rules\
                    // NotAPreviousPassword's docblock).
                    $this->adminAuth->resetPassword($admin, $password);
                },
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => [__($status)]]);
        }

        return redirect()->route('admin.login')->with('status', __('admin.flash.password_reset'));
    }
}
