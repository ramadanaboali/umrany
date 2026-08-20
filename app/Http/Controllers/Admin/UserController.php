<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Core\Services\Admin\UserManagementService;

/**
 * Deliberately minimal — see Modules\Core\Services\Admin\UserManagementService's docblock. Not a
 * full user-management CRUD screen: end users self-register and self-delete.
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
            'user' => $user->load('profile'),
            'activeSessionCount' => $user->tokens()->count(),
        ]);
    }

    public function destroySessions(User $user): RedirectResponse
    {
        $count = $this->users->revokeAllSessions($user);

        return redirect()->route('admin.users.show', $user)->with('status', trans_choice('admin.flash.sessions_revoked', $count, ['count' => $count]));
    }
}
