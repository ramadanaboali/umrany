<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Http\Resources\NotificationResource;

/**
 * Thin CRUD over the framework's own Notifiable::notifications() relation (the "database"
 * channel every Modules\Core\Notifications\BaseUserNotification-based class can target) — no
 * dedicated Service/Repository layer needed for three read/write-a-timestamp actions this simple.
 */
#[Group('Core / Notifications', weight: 4)]
final class NotificationController extends Controller
{
    /**
     * List notifications
     *
     * Unread first, newest first within each group.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()
            ->orderByRaw('read_at IS NOT NULL')
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => NotificationResource::collection($notifications->items()),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
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
