<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LocaleController;
use App\Http\Controllers\Admin\PasswordResetController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ProviderCategoryController;
use App\Http\Controllers\Admin\ProviderController;
use App\Http\Controllers\Admin\ProviderSubcategoryController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SiteSettingsController;
use App\Http\Controllers\Admin\ThemeController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Dashboard Routes
|--------------------------------------------------------------------------
| Session-based (guard `admin`), plain Blade + a dashboard theme — no Livewire, no API surface.
| Deliberately lives at the application root, not inside Modules/*: every other module is
| API-only (docs/architecture/system-architecture.md), and this is the one place that
| intentionally breaks that to serve the admin dashboard. See docs/architecture/admin-portal.md
| and docs/decisions/0007-in-monolith-blade-admin.md for why.
|
| Controllers here call into Modules\Core's Services/Repositories/Models directly (Admin, Role,
| Permission are Core-owned entities — same one-way dependency direction every business module
| already has on Core), and would go through a business module's Contracts\... interface, never
| its Eloquent models, if a future admin screen needs Projects/ECommerce/ERP/AI data. See
| docs/architecture/backend-layering.md.
*/
Route::prefix('admin')->name('admin.')->group(function () {
    // Deliberately outside both guest:admin and auth:admin — the login/forgot-password screens
    // need it too, and it's a self-service preference like profile.edit/update, so no `can:`
    // permission gates it. See App\Http\Controllers\Admin\LocaleController.
    Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

    // Same placement rule as /locale above — fired by a background fetch(), not a page
    // navigation, so it needs no `can:` permission and works pre-login too. See
    // App\Http\Controllers\Admin\ThemeController.
    Route::post('/theme', [ThemeController::class, 'update'])->name('theme.update');

    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [AuthController::class, 'create'])->name('login');
        Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:admin-login')->name('login.store');

        Route::get('/forgot-password', [PasswordResetController::class, 'createForgot'])->name('password.request');
        Route::post('/forgot-password', [PasswordResetController::class, 'storeForgot'])->middleware('throttle:password-reset')->name('password.email');
        Route::get('/reset-password/{token}', [PasswordResetController::class, 'createReset'])->name('password.reset');
        Route::post('/reset-password', [PasswordResetController::class, 'storeReset'])->middleware('throttle:password-reset')->name('password.update');
    });

    Route::middleware('auth:admin')->group(function () {
        Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

        Route::middleware('can:admins.list')->group(function () {
            Route::get('/admins', [AdminController::class, 'index'])->name('admins.index');
        });
        Route::middleware('can:admins.view')->group(function () {
            Route::get('/admins/{admin}/edit', [AdminController::class, 'edit'])->name('admins.edit');
        });
        Route::middleware('can:admins.create')->group(function () {
            Route::get('/admins/create', [AdminController::class, 'create'])->name('admins.create');
            Route::post('/admins', [AdminController::class, 'store'])->name('admins.store');
        });
        Route::middleware('can:admins.update')->group(function () {
            Route::put('/admins/{admin}', [AdminController::class, 'update'])->name('admins.update');
        });
        Route::middleware('can:admins.delete')->group(function () {
            Route::delete('/admins/{admin}', [AdminController::class, 'destroy'])->name('admins.destroy');
        });

        Route::middleware('can:roles.list')->group(function () {
            Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        });
        Route::middleware('can:roles.view')->group(function () {
            Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        });
        Route::middleware('can:roles.create')->group(function () {
            Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
            Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        });
        Route::middleware('can:roles.update')->group(function () {
            Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        });
        Route::middleware('can:roles.delete')->group(function () {
            Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        });

        // Read-only catalog view — gated on the same permission as listing roles, since knowing
        // what permissions exist is only useful alongside being able to see role assignments.
        Route::middleware('can:roles.list')->group(function () {
            Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
        });

        // End users self-register — there is still no admin "create a user" action — but an
        // admin can suspend/reactivate/delete a user's account. See
        // Modules\Core\Services\Admin\UserManagementService and docs/decisions/0027-admin-user-
        // suspend-reactivate.md.
        Route::middleware('can:users.list')->group(function () {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
        });
        Route::middleware('can:users.view')->group(function () {
            Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        });
        Route::middleware('can:users.update')->group(function () {
            Route::put('/users/{user}', [UserController::class, 'updateProfile'])->name('users.update-profile');
            Route::delete('/users/{user}/sessions', [UserController::class, 'destroySessions'])->name('users.sessions.destroy');
            Route::post('/users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
            Route::post('/users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');
        });
        Route::middleware('can:users.delete')->group(function () {
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        });

        // Providers are self-created by the account holder — there is no admin "create a
        // provider" screen — but an admin can view/edit company info and drive the whole
        // verification review + publish/suspend/reactivate lifecycle. See
        // Modules\Core\Services\Admin\ProviderManagementService and
        // docs/decisions/0030-provider-taxonomy-portfolio-and-admin-review.md.
        Route::middleware('can:providers.list')->group(function () {
            Route::get('/providers', [ProviderController::class, 'index'])->name('providers.index');
        });
        Route::middleware('can:providers.view')->group(function () {
            Route::get('/providers/{provider}', [ProviderController::class, 'show'])->name('providers.show');
        });
        Route::middleware('can:providers.update')->group(function () {
            Route::put('/providers/{provider}', [ProviderController::class, 'update'])->name('providers.update');
            Route::post('/providers/{provider}/approve', [ProviderController::class, 'approve'])->name('providers.approve');
            Route::post('/providers/{provider}/reject', [ProviderController::class, 'reject'])->name('providers.reject');
            Route::post('/providers/{provider}/suspend', [ProviderController::class, 'suspend'])->name('providers.suspend');
            Route::post('/providers/{provider}/reactivate', [ProviderController::class, 'reactivate'])->name('providers.reactivate');

            // FR-PROVIDER-004 — same providers.update permission, no new one: applying imported
            // government data is part of the same admin-side provider-editing workflow.
            Route::post('/providers/{provider}/government-verification/{governmentVerificationRequest}/apply', [ProviderController::class, 'applyGovernmentVerification'])->name('providers.government-verification.apply');
        });

        // Full 5-action CRUD — categories genuinely are admin-created/edited/deleted (no
        // self-service creation path). Subcategory management (nested under a category) is
        // gated by these same permissions, no separate set. See
        // docs/decisions/0033-provider-category-admin-management.md.
        Route::middleware('can:provider-categories.list')->group(function () {
            Route::get('/provider-categories', [ProviderCategoryController::class, 'index'])->name('provider-categories.index');
        });
        Route::middleware('can:provider-categories.view')->group(function () {
            Route::get('/provider-categories/{providerCategory}/subcategories', [ProviderSubcategoryController::class, 'index'])->name('provider-categories.subcategories.index');
        });
        Route::middleware('can:provider-categories.create')->group(function () {
            Route::post('/provider-categories', [ProviderCategoryController::class, 'store'])->name('provider-categories.store');
            Route::post('/provider-categories/{providerCategory}/subcategories', [ProviderSubcategoryController::class, 'store'])->name('provider-categories.subcategories.store');
        });
        Route::middleware('can:provider-categories.update')->group(function () {
            Route::put('/provider-categories/{providerCategory}', [ProviderCategoryController::class, 'update'])->name('provider-categories.update');
            Route::put('/provider-categories/{providerCategory}/subcategories/{providerSubcategory}', [ProviderSubcategoryController::class, 'update'])->name('provider-categories.subcategories.update');
        });
        Route::middleware('can:provider-categories.delete')->group(function () {
            Route::delete('/provider-categories/{providerCategory}', [ProviderCategoryController::class, 'destroy'])->name('provider-categories.destroy');
            Route::delete('/provider-categories/{providerCategory}/subcategories/{providerSubcategory}', [ProviderSubcategoryController::class, 'destroy'])->name('provider-categories.subcategories.destroy');
        });

        // Singleton config row (Modules\Core\Models\SiteSetting::current()) — 2-action permission
        // set, not the usual 5: there's no list/create/delete for a row that always exists. Same
        // documented asymmetry as users.* above. See docs/architecture/admin-portal.md.
        Route::middleware('can:settings.view')->group(function () {
            Route::get('/settings', [SiteSettingsController::class, 'edit'])->name('settings.edit');
        });
        Route::middleware('can:settings.update')->group(function () {
            Route::put('/settings', [SiteSettingsController::class, 'update'])->name('settings.update');
            Route::delete('/settings/logo', [SiteSettingsController::class, 'destroyLogo'])->name('settings.logo.destroy');
        });
    });
});
