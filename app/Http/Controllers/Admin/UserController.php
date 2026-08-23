<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\SuspendUserRequest;
use App\Http\Requests\Admin\UpdateUserProfileRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Core\Models\City;
use Modules\Core\Models\Country;
use Modules\Core\Services\Admin\UserManagementService;
use Modules\Core\Services\ProfileService;

/**
 * End users self-register — there is still no admin "create a user" action — but an admin can
 * now suspend/reactivate/delete a user's account, and (per the "Administrators may update user
 * profiles according to assigned permissions" requirement) edit a user's own profile fields. See
 * docs/decisions/0027-admin-user-suspend-reactivate.md.
 */
final class UserController extends Controller
{
    public function __construct(
        private readonly UserManagementService $users,
        private readonly ProfileService $profiles,
    ) {}

    public function index(Request $request): View
    {
        return view('admin.users.index', [
            'users' => $this->users->paginate(search: $request->string('search')->value() ?: null),
            'search' => $request->string('search')->value(),
        ]);
    }

    public function show(User $user): View
    {
        $user->load(['profile', 'suspendedBy']);

        return view('admin.users.show', [
            'user' => $user,
            'activeSessionCount' => $user->tokens()->count(),
            'countries' => Country::query()->where('is_active', true)->orderBy('name_en')->get(),
            'cities' => $user->profile?->country_id
                ? City::query()->where('country_id', $user->profile->country_id)->where('is_active', true)->orderBy('name_en')->get()
                : collect(),
        ]);
    }

    /**
     * Editable fields mirror the end-user's own PUT /api/v1/core/profile exactly (Full Name,
     * Email, Mobile, Address, Country, City) — Avatar/Language/Currency stay self-service-only,
     * they're personal preferences, not administrative data. Reuses
     * Modules\Core\Services\ProfileService::updateProfile() directly, so email/mobile changes
     * still clear the verified-at timestamp and re-trigger verification exactly as they do for a
     * self-service update — no duplicated business logic.
     */
    public function updateProfile(UpdateUserProfileRequest $request, User $user): RedirectResponse
    {
        $this->profiles->updateProfile($user, $request->validated());

        return redirect()->route('admin.users.show', $user)->with('status', __('admin.flash.user_profile_updated'));
    }

    public function destroySessions(User $user): RedirectResponse
    {
        $count = $this->users->revokeAllSessions($user);

        return redirect()->route('admin.users.show', $user)->with('status', trans_choice('admin.flash.sessions_revoked', $count, ['count' => $count]));
    }

    public function suspend(SuspendUserRequest $request, User $user): RedirectResponse
    {
        $this->users->suspend($user, Auth::guard('admin')->user(), $request->string('reason')->value());

        return redirect()->route('admin.users.show', $user)->with('status', __('admin.flash.user_suspended'));
    }

    public function reactivate(User $user): RedirectResponse
    {
        $this->users->reactivate($user);

        return redirect()->route('admin.users.show', $user)->with('status', __('admin.flash.user_reactivated'));
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->users->delete($user);

        return redirect()->route('admin.users.index')->with('status', __('admin.flash.user_deleted'));
    }
}
