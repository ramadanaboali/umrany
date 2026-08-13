<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Core\Http\Resources\CityResource;
use Modules\Core\Http\Resources\CountryResource;
use Modules\Core\Http\Resources\CurrencyResource;
use Modules\Core\Models\City;
use Modules\Core\Models\Country;
use Modules\Core\Models\Currency;

/**
 * Public read-only master data — needed to populate the country/city/currency pickers that
 * FR-PROFILE-002/005/006 and FR-PROVIDER-002 require, without a separate client-side copy of
 * this data going stale. Writes to these tables are an Admin-portal concern (docs/modules/core.md
 * § Admin), not exposed here.
 */
final class MasterDataController extends Controller
{
    /**
     * List countries
     *
     * @group Core / Master data
     *
     * @response 200 scenario="success" {"data": [{"id": 1, "code": "SA", "name_en": "Saudi Arabia"}]}
     */
    public function countries(): JsonResponse
    {
        return response()->json([
            'data' => CountryResource::collection(Country::query()->where('is_active', true)->orderBy('name_en')->get()),
        ]);
    }

    /**
     * List cities for a country
     *
     * @group Core / Master data
     *
     * @urlParam country integer required Example: 1
     *
     * @response 200 scenario="success" {"data": [{"id": 4, "country_id": 1, "name_en": "Riyadh"}]}
     */
    public function cities(Country $country): JsonResponse
    {
        return response()->json([
            'data' => CityResource::collection($country->cities()->where('is_active', true)->orderBy('name_en')->get()),
        ]);
    }

    /**
     * List currencies
     *
     * @group Core / Master data
     *
     * @response 200 scenario="success" {"data": [{"id": 1, "code": "SAR", "symbol": "SAR"}]}
     */
    public function currencies(): JsonResponse
    {
        return response()->json([
            'data' => CurrencyResource::collection(Currency::query()->where('is_active', true)->orderBy('code')->get()),
        ]);
    }
}
