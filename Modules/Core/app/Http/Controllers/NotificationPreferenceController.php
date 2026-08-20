<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Http\Requests\Notification\UpdateNotificationPreferencesRequest;
use Modules\Core\Http\Resources\NotificationPreferenceMatrixResource;
use Modules\Core\Services\NotificationPreferenceService;

#[Group('Core / Notifications', weight: 4)]
final class NotificationPreferenceController extends Controller
{
    public function __construct(
        private readonly NotificationPreferenceService $preferences,
    ) {}

    /**
     * Get notification preferences
     *
     * Returns every notification event and its current in-app/email toggle — events with no
     * stored row yet show their default (both channels on). `mandatory: true` events can't be
     * disabled.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => new NotificationPreferenceMatrixResource($this->preferences->matrixFor($request->user()))]);
    }

    /**
     * Update notification preferences
     *
     * Accepts the full or partial matrix; an attempt to disable a mandatory event is silently
     * ignored rather than rejecting the whole request.
     */
    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $matrix = $this->preferences->update($request->user(), $request->input('preferences'));

        return response()->json(['data' => new NotificationPreferenceMatrixResource($matrix)]);
    }
}
