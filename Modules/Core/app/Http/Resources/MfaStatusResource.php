<?php

declare(strict_types=1);

namespace Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the array returned by Modules\Core\Services\MfaService::status() — a plain array here
 * rather than a model: `array{enabled: bool, confirmed_at: ?\Carbon\CarbonInterface,
 * recovery_codes_remaining: int}`.
 */
final class MfaStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'enabled' => $this->resource['enabled'],
            'confirmed_at' => $this->resource['confirmed_at'],
            'recovery_codes_remaining' => $this->resource['recovery_codes_remaining'],
        ];
    }
}
