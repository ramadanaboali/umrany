<?php

declare(strict_types=1);

namespace Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\PersonalAccessToken;

/** @mixin PersonalAccessToken */
final class SessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $expiration = config('sanctum.expiration');

        return [
            'id' => $this->id,
            'device_name' => $this->name,
            'last_used_at' => $this->last_used_at,
            'created_at' => $this->created_at,
            'expires_at' => $expiration !== null ? $this->created_at?->addMinutes((int) $expiration) : null,
            'is_current' => $request->user()?->currentAccessToken()?->id === $this->id,
        ];
    }
}
