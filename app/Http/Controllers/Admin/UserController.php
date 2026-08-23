<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\SuspendUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Core\Services\Admin\UserManagementService;

/**
 * End users self-register — there is still no admin "create a user" action — but an admin can
 * now suspend/reactivate/delete a user's account. See
 * docs/decisions/0027-admin-user-suspend-reactivate.md.
 */
final class UserController extends Controller
{
    public function __construct(
        private readonly UserManagementService $users,
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
        return view('admin.users.show', [
            'user' => $user->load(['profile', 'suspendedBy']),
            'activeSessionCount' => $user->tokens()->count(),
        ]);
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
