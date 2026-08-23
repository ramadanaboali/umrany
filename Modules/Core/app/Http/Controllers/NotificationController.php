<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Core\Http\Resources\NotificationResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Thin CRUD over the framework's own Notifiable::notifications() relation (the "database"
 * channel every Modules\Core\Notifications\BaseUserNotification-based class can target) — no
 * dedicated Service/Repository layer needed for actions this simple.
 */
#[Group('Core / Notifications', weight: 4)]
final class NotificationController extends Controller
{
    private const MAX_PER_PAGE = 100;

    /**
     * List notifications
     *
     * Unread first, newest first within each group. Paginated — see CLAUDE.md Rule 10.
     */
    #[QueryParameter('filter[read]', description: 'true for read notifications, false for unread.', type: 'boolean')]
    #[QueryParameter('filter[type]', description: 'Partial match against the notification class basename, e.g. "AccountSuspended".', type: 'string')]
    #[QueryParameter('per_page', description: 'Page size, 1-100.', type: 'integer', default: 20)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $notifications = QueryBuilder::for($request->user()->notifications())
            ->allowedFilters(
                AllowedFilter::callback('read', function ($query, $value) {
                    filter_var($value, FILTER_VALIDATE_BOOLEAN)
                        ? $query->whereNotNull('read_at')
                        : $query->whereNull('read_at');
                }),
                AllowedFilter::callback('type', fn ($query, $value) => $query->where('type', 'like', '%'.$value.'%')),
            )
            ->orderByRaw('read_at IS NOT NULL')
            ->latest()
            ->paginate($this->perPage($request));

        return NotificationResource::collection($notifications);
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 20), 1), self::MAX_PER_PAGE);
    }

    /**
     * Get notification counts
     */
    public function count(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'total' => $request->user()->notifications()->count(),
                'unread' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }

    /**
     * Mark one notification read
     */
    public function markRead(Request $request, string $notification): JsonResponse
    {
        $request->user()->notifications()->where('id', $notification)->firstOrFail()->markAsRead();

        return response()->json(null, 204);
    }

    /**
     * Mark all notifications read
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(null, 204);
    }
}
