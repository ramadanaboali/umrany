<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\StoreAdminRequest;
use App\Http\Requests\Admin\UpdateAdminRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Core\Models\Admin;
use Modules\Core\Services\Admin\AdminManagementService;
use Spatie\Permission\Models\Role;

final class AdminController extends Controller
{
    public function __construct(
        private readonly AdminManagementService $admins,
    ) {}

    public function index(): View
    {
        return view('admin.admins.index', [
            'admins' => $this->admins->paginate(),
        ]);
    }

    public function create(): View
    {
        return view('admin.admins.create', [
            'roles' => Role::query()->where('guard_name', 'admin')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreAdminRequest $request): RedirectResponse
    {
        $this->admins->create($request->validated(), Auth::guard('admin')->user());

        return redirect()->route('admin.admins.index')->with('status', 'Admin created.');
    }

    public function edit(Admin $admin): View
    {
        return view('admin.admins.edit', [
            'admin' => $admin->load('roles'),
            'roles' => Role::query()->where('guard_name', 'admin')->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateAdminRequest $request, Admin $admin): RedirectResponse
    {
        $this->admins->update($admin, $request->validated(), Auth::guard('admin')->user());

        return redirect()->route('admin.admins.index')->with('status', 'Admin updated.');
    }
}
