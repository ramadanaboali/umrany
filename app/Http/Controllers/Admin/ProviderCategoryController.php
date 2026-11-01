<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\StoreProviderCategoryRequest;
use App\Http\Requests\Admin\UpdateProviderCategoryRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Core\Models\ProviderCategory;
use Modules\Core\Services\Admin\ProviderCategoryManagementService;

/**
 * Admin management of the provider category taxonomy — previously seed-managed only. See
 * docs/decisions/0033-provider-category-admin-management.md. Unpaginated (a small, bounded
 * catalog, same exception as the public GET /provider-categories picker).
 */
final class ProviderCategoryController extends Controller
{
    public function __construct(
        private readonly ProviderCategoryManagementService $categories,
    ) {}

    public function index(): View
    {
        return view('admin.provider-categories.index', [
            'categories' => $this->categories->all(),
        ]);
    }

    public function store(StoreProviderCategoryRequest $request): RedirectResponse
    {
        $this->categories->create($request->validated());

        return redirect()->route('admin.provider-categories.index')->with('status', __('admin.flash.provider_category_created'));
    }

    public function update(UpdateProviderCategoryRequest $request, ProviderCategory $providerCategory): RedirectResponse
    {
        $this->categories->update($providerCategory, $request->validated());

        return redirect()->route('admin.provider-categories.index')->with('status', __('admin.flash.provider_category_updated'));
    }

    public function destroy(ProviderCategory $providerCategory): RedirectResponse
    {
        $this->categories->delete($providerCategory);

        return redirect()->route('admin.provider-categories.index')->with('status', __('admin.flash.provider_category_deleted'));
    }
}
