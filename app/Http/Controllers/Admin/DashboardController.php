<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Core\Services\Admin\AdminManagementService;

/**
 * Deliberately thin for now — this pass only builds identity/RBAC. Cross-product operational
 * dashboards (docs/business/roadmap.md Phase 7) land once Projects/ECommerce/ERP/AI have real
 * data to aggregate, sourced through each module's Contracts, never their Eloquent models
 * directly (see docs/architecture/admin-portal.md).
 */
final class DashboardController extends Controller
{
    public function __construct(
        private readonly AdminManagementService $admins,
    ) {}

    public function index(): View
    {
        return view('admin.dashboard', [
            // Excludes Super Admins — see Admin::excludingSuperAdmins() and
            // docs/architecture/admin-portal.md.
            'adminCount' => $this->admins->countRegular(),
        ]);
    }
}
