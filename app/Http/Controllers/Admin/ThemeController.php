<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\UpdateThemeModeRequest;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Enums\ThemeMode;
use Modules\Core\Services\Admin\AdminManagementService;

/**
 * Fired by a background fetch() from resources/js/admin-theme.js on every dark/light toggle, not
 * a full-page form submit — the client-side toggle stays instant, this just syncs the choice to
 * the account so it follows the admin across devices/browsers (localStorage already covers "this
 * browser" durability on its own). A no-op for a guest request — there is no admin record to
 * persist to on the pre-login pages, but the client sends its own localStorage value in that
 * case anyway, so nothing needs a permission gate here either. See
 * docs/decisions/0021-velzon-material-admin-theme.md.
 */
final class ThemeController extends Controller
{
    public function __construct(
        private readonly AdminManagementService $admins,
    ) {}

    public function update(UpdateThemeModeRequest $request): Response
    {
        if ($admin = Auth::guard('admin')->user()) {
            $mode = ThemeMode::from($request->string('mode')->value());
            $this->admins->updateThemeMode($admin, $mode);
        }

        return response()->noContent();
    }
}
