<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\UpdateLocaleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Modules\Core\Enums\Language;
use Modules\Core\Services\Admin\AdminManagementService;

/**
 * Deliberately outside both the `guest:admin` and `auth:admin` route groups — the login/forgot-
 * password screens need it too (an Arabic-speaking admin must be able to read the login page
 * before signing in). See docs/decisions/0022-admin-dashboard-en-ar-localization.md.
 */
final class LocaleController extends Controller
{
    private const COOKIE = 'admin_locale';

    public function __construct(
        private readonly AdminManagementService $admins,
    ) {}

    public function update(UpdateLocaleRequest $request): RedirectResponse
    {
        $language = Language::from($request->string('language')->value());

        // A long-lived cookie, not the session — this is what lets the choice survive the
        // login/logout boundary (a fresh session starts empty) and outlive a short session
        // lifetime, the same "remember this browser" durability dark mode already gets from
        // localStorage. See App\Http\Middleware\SetAdminLocale and AuthController::store().
        Cookie::queue(self::COOKIE, $language->value, 60 * 24 * 365);

        if ($admin = Auth::guard('admin')->user()) {
            $this->admins->updateLocale($admin, $language);
        }

        App::setLocale($language->value);

        return back()->with('status', __('admin.flash.locale_updated'));
    }
}
