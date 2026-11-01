<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Core\Http\Resources\ProviderCategoryResource;
use Modules\Core\Models\ProviderCategory;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * FR-PROVIDER-003: public read-only business-category picker list. Deliberately unpaginated
 * (root CLAUDE.md Rule 10's named exception) — a fixed, bounded 10-row catalog backing a
 * category picker, same documented exception as GET /countries.
 */
#[Group('Core / Master data', weight: 5)]
final class ProviderCategoryController extends Controller
{
    /**
     * List provider categories
     */
    #[QueryParameter('filter[search]', description: 'Partial match against name_en or name_ar.', type: 'string')]
    public function index(): JsonResponse
    {
        $categories = QueryBuilder::for(ProviderCategory::query())
            ->allowedFilters(
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($inner) use ($value) {
                        $inner->where('name_en', 'like', '%'.$value.'%')
                            ->orWhere('name_ar', 'like', '%'.$value.'%');
                    });
                }),
            )
            ->orderBy('display_order')
            ->get();

        return response()->json(['data' => ProviderCategoryResource::collection($categories)]);
    }
}
