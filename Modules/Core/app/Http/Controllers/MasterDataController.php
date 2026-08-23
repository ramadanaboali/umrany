<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Core\Http\Resources\CityResource;
use Modules\Core\Http\Resources\CountryResource;
use Modules\Core\Http\Resources\CurrencyResource;
use Modules\Core\Models\City;
use Modules\Core\Models\Country;
use Modules\Core\Models\Currency;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Public read-only master data — needed to populate the country/city/currency pickers that
 * FR-PROFILE-002/005/006 and FR-PROVIDER-002 require, without a separate client-side copy of
 * this data going stale. Writes to these tables are an Admin-portal concern (docs/modules/core.md
 * § Admin), not exposed here.
 *
 * Deliberately unpaginated (CLAUDE.md Rule 10's named exception) — these back small picker/
 * dropdown UIs, not open-ended browsing. `is_active` stays a server-enforced constant, never a
 * client-controllable filter — these are public, unauthenticated endpoints.
 */
#[Group('Core / Master data', weight: 5)]
final class MasterDataController extends Controller
{
    /**
     * List countries
     */
    #[QueryParameter('filter[search]', description: 'Partial match against name_en or name_ar.', type: 'string')]
    public function countries(): JsonResponse
    {
        return response()->json([
            'data' => CountryResource::collection(
                $this->searchable(Country::query()->where('is_active', true))->orderBy('name_en')->get()
            ),
        ]);
    }

    /**
     * List cities for a country
     */
    #[QueryParameter('filter[search]', description: 'Partial match against name_en or name_ar.', type: 'string')]
    public function cities(Country $country): JsonResponse
    {
        return response()->json([
            'data' => CityResource::collection(
                $this->searchable($country->cities()->where('is_active', true))->orderBy('name_en')->get()
            ),
        ]);
    }

    /**
     * List currencies
     */
    #[QueryParameter('filter[search]', description: 'Partial match against name_en or name_ar.', type: 'string')]
    public function currencies(): JsonResponse
    {
        return response()->json([
            'data' => CurrencyResource::collection(
                $this->searchable(Currency::query()->where('is_active', true))->orderBy('code')->get()
            ),
        ]);
    }

    /**
     * @template TModel of City|Country|Currency
     *
     * @param  Builder<TModel>|Relation<TModel, *, *>  $query
     * @return QueryBuilder<TModel>
     */
    private function searchable(Builder|Relation $query): QueryBuilder
    {
        return QueryBuilder::for($query)
            ->allowedFilters(
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($inner) use ($value) {
                        $inner->where('name_en', 'like', '%'.$value.'%')
                            ->orWhere('name_ar', 'like', '%'.$value.'%');
                    });
                }),
            );
    }
}
