<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Http\Resources\SessionResource;

/**
 * FR-AUTH-004: manage active sessions across devices. A "session" here is a Sanctum personal
 * access token — there is no separate custom sessions table, since Sanctum's own token table
 * already is the per-device session record.
 */
#[Group('Core / Auth', weight: 1)]
final class SessionController extends Controller
{
    /**
     * List active sessions
     *
     * Excludes any token already past config('sanctum.expiration') — Sanctum's guard already
     * rejects those, so a raw token-table listing without this filter would show "active"
     * sessions that can no longer actually authenticate.
     */
    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()->latest();

        $expiration = config('sanctum.expiration');
        if ($expiration !== null) {
            $tokens->where('created_at', '>', now()->subMinutes((int) $expiration));
        }

        return response()->json(['data' => SessionResource::collection($tokens->get())]);
    }

    /**
     * Revoke a session
     *
     * `$session` is the token id from GET .../auth/sessions.
     */
    #[Response(404, description: 'The session id does not exist, or belongs to a different user.')]
    public function destroy(Request $request, int $session): JsonResponse
    {
        $request->user()->tokens()->where('id', $session)->firstOrFail()->delete();

        return response()->json(null, 204);
    }

    /**
     * Revoke every other session
     *
     * Keeps the token used to authenticate this request; use POST .../auth/logout-all to also
     * revoke it.
     */
    public function destroyOthers(Request $request): JsonResponse
    {
        $user = $request->user();
        // @phpstan-ignore nullsafe.neverNull (see AuthService::changePassword()'s identical note)
        $user->tokens()->where('id', '!=', $user->currentAccessToken()?->id)->delete();

        return response()->json(null, 204);
    }
}
