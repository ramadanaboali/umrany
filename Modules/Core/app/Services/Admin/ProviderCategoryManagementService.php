<?php

declare(strict_types=1);

namespace Modules\Core\Services\Admin;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\ProviderCategory;
use Modules\Core\Models\ProviderSubcategory;

/**
 * Admin-side management of the provider category/subcategory taxonomy — previously seed-managed
 * only (docs/decisions/0030-provider-taxonomy-portfolio-and-admin-review.md,
 * 0032-provider-subcategories.md). See docs/decisions/0033-provider-category-admin-management.md.
 * Operates directly on the models (no dedicated Repository) — matching the more recent
 * ProviderManagementService precedent, not the older Role/User Repository-backed one; this
 * taxonomy has no request-shape complex enough to warrant one.
 */
final class ProviderCategoryManagementService
{
    /**
     * @return Collection<int, ProviderCategory>
     */
    public function all(): Collection
    {
        return ProviderCategory::query()->withCount('providers')->orderBy('display_order')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ProviderCategory
    {
        return ProviderCategory::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ProviderCategory $category, array $data): ProviderCategory
    {
        $category->update($data);

        return $category;
    }

    public function delete(ProviderCategory $category): void
    {
        if ($category->providers()->exists()) {
            throw ValidationException::withMessages([
                'category' => [__('core::admin.category_still_assigned_to_providers')],
            ]);
        }

        $category->delete();
    }

    /**
     * @return Collection<int, ProviderSubcategory>
     */
    public function subcategoriesFor(ProviderCategory $category): Collection
    {
        return $category->subcategories()->withCount('providers')->orderBy('display_order')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createSubcategory(ProviderCategory $category, array $data): ProviderSubcategory
    {
        return $category->subcategories()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSubcategory(ProviderSubcategory $subcategory, array $data): ProviderSubcategory
    {
        $subcategory->update($data);

        return $subcategory;
    }

    public function deleteSubcategory(ProviderSubcategory $subcategory): void
    {
        if ($subcategory->providers()->exists()) {
            throw ValidationException::withMessages([
                'subcategory' => [__('core::admin.subcategory_still_assigned_to_providers')],
            ]);
        }

        $subcategory->delete();
    }
}
