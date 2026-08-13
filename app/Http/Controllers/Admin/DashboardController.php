<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Core\Models\Admin;

/**
 * Deliberately thin for now — this pass only builds identity/RBAC. Cross-product operational
 * dashboards (docs/business/roadmap.md Phase 7) land once Projects/ECommerce/ERP/AI have real
 * data to aggregate, sourced through each module's Contracts, never their Eloquent models
 * directly (see docs/architecture/admin-portal.md).
 */
final class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'adminCount' => Admin::query()->count(),
        ]);
    }
}
