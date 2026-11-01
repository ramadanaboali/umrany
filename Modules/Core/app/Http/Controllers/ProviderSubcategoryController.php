<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Core\Http\Resources\ProviderSubcategoryResource;
use Modules\Core\Models\ProviderSubcategory;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * FR-PROVIDER-003: public read-only subcategory picker list, same shape/exception as
 * ProviderCategoryController — see docs/decisions/0032-provider-subcategories.md.
 */
#[Group('Core / Master data', weight: 5)]
final class ProviderSubcategoryController extends Controller
{
    /**
     * List provider subcategories
     */
    #[QueryParameter('filter[provider_category_id]', description: 'Exact match.', type: 'integer')]
    #[QueryParameter('filter[search]', description: 'Partial match against name_en or name_ar.', type: 'string')]
    public function index(): JsonResponse
    {
        $subcategories = QueryBuilder::for(ProviderSubcategory::query())
            ->allowedFilters(
                AllowedFilter::exact('provider_category_id'),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($inner) use ($value) {
                        $inner->where('name_en', 'like', '%'.$value.'%')
                            ->orWhere('name_ar', 'like', '%'.$value.'%');
                    });
                }),
            )
            ->orderBy('display_order')
            ->get();

        return response()->json(['data' => ProviderSubcategoryResource::collection($subcategories)]);
    }
}
