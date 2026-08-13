<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Core\Services\Admin\AdminAuthService;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AdminAuthService $adminAuth,
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
