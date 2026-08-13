<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\UpdateOwnProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Core\Services\Admin\AdminManagementService;

final class ProfileController extends Controller
{
    public function __construct(
        private readonly AdminManagementService $admins,
    ) {}

    public function edit(): View
    {
        return view('admin.profile.edit', ['admin' => Auth::guard('admin')->user()]);
    }

    public function update(UpdateOwnProfileRequest $request): RedirectResponse
    {
        $this->admins->updateOwnProfile(Auth::guard('admin')->user(), [
            'name' => $request->string('name')->value(),
            'phone' => $request->string('phone')->value() ?: null,
            'password' => $request->filled('password') ? $request->string('password')->value() : null,
        ]);

        return back()->with('status', 'Profile updated.');
    }
}
