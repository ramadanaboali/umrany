<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Core\Http\Resources\SessionResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * FR-AUTH-004: manage active sessions across devices. A "session" here is a Sanctum personal
 * access token — there is no separate custom sessions table, since Sanctum's own token table
 * already is the per-device session record.
 */
#[Group('Core / Auth', weight: 1)]
final class SessionController extends Controller
{
    private const MAX_PER_PAGE = 100;

    /**
     * List active sessions
     *
     * Excludes any token already past config('sanctum.expiration') — Sanctum's guard already
     * rejects those, so a raw token-table listing without this filter would show "active"
     * sessions that can no longer actually authenticate. Paginated — see CLAUDE.md Rule 10.
     */
    #[QueryParameter('filter[device_name]', description: 'Partial match against the session/device name.', type: 'string')]
    #[QueryParameter('per_page', description: 'Page size, 1-100.', type: 'integer', default: 20)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $tokens = QueryBuilder::for($request->user()->tokens())
            ->allowedFilters(AllowedFilter::partial('device_name', 'name'))
            ->latest();

        $expiration = config('sanctum.expiration');
        if ($expiration !== null) {
            $tokens->where('created_at', '>', now()->subMinutes((int) $expiration));
        }

        $perPage = min(max($request->integer('per_page', 20), 1), self::MAX_PER_PAGE);

        return SessionResource::collection($tokens->paginate($perPage));
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
