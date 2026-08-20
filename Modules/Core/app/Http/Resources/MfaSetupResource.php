<?php

declare(strict_types=1);

namespace Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the array returned by Modules\Core\Services\MfaService::beginEnrollment() — an ad-hoc
 * setup payload, not a single Eloquent-backed resource, so `$this->resource` is a plain array
 * here rather than a model: `array{setting: UserMfaSetting, otpauth_uri: string, qr_svg: string}`.
 */
final class MfaSetupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Manual-entry fallback for an authenticator app that can't scan the QR code —
            // deliberately the only place this codebase ever returns the plaintext secret.
            'secret' => $this->resource['setting']->secret,
            'otpauth_uri' => $this->resource['otpauth_uri'],
            'qr_svg' => $this->resource['qr_svg'],
        ];
    }
}
