<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Http\Resources\SessionResource;

/**
 * FR-AUTH-004: manage active sessions across devices. A "session" here is a Sanctum personal
 * access token — there is no separate custom sessions table, since Sanctum's own token table
 * already is the per-device session record.
 */
final class SessionController extends Controller
{
    /**
     * List active sessions
     *
     * @group Core / Auth
     *
     * @authenticated
     *
     * @response 200 scenario="success" {"data": [{"id": 1, "device_name": "api-token", "is_current": true}]}
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => SessionResource::collection($request->user()->tokens()->latest()->get()),
        ]);
    }

    /**
     * Revoke a session
     *
     * @group Core / Auth
     *
     * @authenticated
     *
     * @urlParam session integer required The token id from GET .../auth/sessions. Example: 3
     *
     * @response 204
     * @response 404 scenario="not found or not yours" {"message": "No query results for model."}
     */
    public function destroy(Request $request, int $session): JsonResponse
    {
        $request->user()->tokens()->where('id', $session)->firstOrFail()->delete();

        return response()->json(null, 204);
    }
}
