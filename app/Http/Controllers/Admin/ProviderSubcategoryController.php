<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\StoreProviderSubcategoryRequest;
use App\Http\Requests\Admin\UpdateProviderSubcategoryRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Core\Models\ProviderCategory;
use Modules\Core\Models\ProviderSubcategory;
use Modules\Core\Services\Admin\ProviderCategoryManagementService;

/**
 * Admin management of subcategories nested under one category — gated by the same
 * provider-categories.* permissions, no separate permission set. See
 * docs/decisions/0033-provider-category-admin-management.md.
 */
final class ProviderSubcategoryController extends Controller
{
    public function __construct(
        private readonly ProviderCategoryManagementService $categories,
    ) {}

    public function index(ProviderCategory $providerCategory): View
    {
        return view('admin.provider-categories.subcategories', [
            'category' => $providerCategory,
            'subcategories' => $this->categories->subcategoriesFor($providerCategory),
        ]);
    }

    public function store(StoreProviderSubcategoryRequest $request, ProviderCategory $providerCategory): RedirectResponse
    {
        $this->categories->createSubcategory($providerCategory, $request->validated());

        return redirect()->route('admin.provider-categories.subcategories.index', $providerCategory)->with('status', __('admin.flash.provider_subcategory_created'));
    }

    public function update(UpdateProviderSubcategoryRequest $request, ProviderCategory $providerCategory, ProviderSubcategory $providerSubcategory): RedirectResponse
    {
        $this->categories->updateSubcategory($providerSubcategory, $request->validated());

        return redirect()->route('admin.provider-categories.subcategories.index', $providerCategory)->with('status', __('admin.flash.provider_subcategory_updated'));
    }

    public function destroy(ProviderCategory $providerCategory, ProviderSubcategory $providerSubcategory): RedirectResponse
    {
        $this->categories->deleteSubcategory($providerSubcategory);

        return redirect()->route('admin.provider-categories.subcategories.index', $providerCategory)->with('status', __('admin.flash.provider_subcategory_deleted'));
    }
}
