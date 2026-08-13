<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Contracts\UserCapabilityResolver;
use Modules\Core\Http\Resources\UserCapabilitiesResource;

final class CapabilityController extends Controller
{
    /**
     * My capabilities
     *
     * The computed "which hats does this account wear" read model (Project Owner / Supplier /
     * ERP User are never stored roles — see docs/business/personas.md). UI-hint only: every
     * protected endpoint still independently re-checks entitlement server-side regardless of
     * what this returns.
     *
     * @group Core / Profile
     *
     * @authenticated
     *
     * @response 200 scenario="success" {"data": {"is_provider": true, "provider_verified": false, "has_ecommerce_access": false, "has_erp_access": false, "max_project_offers": 3}}
     */
    public function show(Request $request, UserCapabilityResolver $resolver): JsonResponse
    {
        $capabilities = $resolver->capabilitiesFor((int) $request->user()->getAuthIdentifier());

        return response()->json(['data' => new UserCapabilitiesResource($capabilities)]);
    }
}
