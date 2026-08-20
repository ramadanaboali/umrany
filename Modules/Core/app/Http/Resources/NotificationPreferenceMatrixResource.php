<?php

declare(strict_types=1);

namespace Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the array returned by Modules\Core\Services\NotificationPreferenceService::matrixFor()/
 * update() — a plain array of per-event rows, not a single Eloquent-backed resource:
 * `array<int, array{event_type: string, mandatory: bool, in_app: bool, email: bool}>`.
 */
final class NotificationPreferenceMatrixResource extends JsonResource
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
