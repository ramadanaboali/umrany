<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Core\Enums\Language;
use Modules\Core\Services\Admin\AdminAuthService;
use Modules\Core\Services\Admin\AdminManagementService;

final class AuthController extends Controller
{
    /** Must match LocaleController::COOKIE / SetAdminLocale::COOKIE. */
    private const LOCALE_COOKIE = 'admin_locale';

    public function __construct(
        private readonly AdminAuthService $adminAuth,
        private readonly AdminManagementService $admins,
    ) {}

    public function create(): View
    {
        return view('admin.auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $admin = $this->adminAuth->authenticate($request->string('email')->value(), $request->string('password')->value());

        Auth::guard('admin')->login($admin);
        $request->session()->regenerate();

        $this->adminAuth->recordLogin($admin, $request->ip());

        // Adopt whatever language they were reading the login page in as their saved preference
        // — without this, SetAdminLocale's DB-wins-when-authenticated priority would silently
        // revert them to their old preference (or English) the instant they sign in, discarding
        // the choice they just explicitly made. See docs/decisions/0022-admin-dashboard-en-ar-
        // localization.md.
        $cookieLanguage = Language::tryFrom((string) $request->cookie(self::LOCALE_COOKIE));
        if ($cookieLanguage !== null && $cookieLanguage !== $admin->preferred_language) {
            $this->admins->updateLocale($admin, $cookieLanguage);
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
