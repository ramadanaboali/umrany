<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Contracts\UserCapabilityResolver;
use Modules\Core\Http\Resources\UserCapabilitiesResource;

#[Group('Core / Profile', weight: 2)]
final class CapabilityController extends Controller
{
    /**
     * My capabilities
     *
     * The computed "which hats does this account wear" read model (Project Owner / Supplier /
     * ERP User are never stored roles — see docs/business/personas.md). UI-hint only: every
     * protected endpoint still independently re-checks entitlement server-side regardless of
     * what this returns.
     */
    public function show(Request $request, UserCapabilityResolver $resolver): JsonResponse
    {
        $capabilities = $resolver->capabilitiesFor((int) $request->user()->getAuthIdentifier());

        return response()->json(['data' => new UserCapabilitiesResource($capabilities)]);
    }
}
