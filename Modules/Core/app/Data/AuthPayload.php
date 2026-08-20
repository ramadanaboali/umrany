<?php

declare(strict_types=1);

namespace Modules\Core\Data;

use App\Models\User;
use Modules\Core\Models\Provider;
use Spatie\LaravelData\Data;

/**
 * Wraps everything a completed login/register/MFA-challenge response returns — built once in
 * Modules\Core\Services\AuthService::issueAuthenticatedSession() and rendered by
 * Modules\Core\Http\Resources\AuthPayloadResource, so register(), a direct login, and a
 * completed MFA challenge all produce the identical enriched shape instead of three separate
 * ad-hoc response arrays. See docs/decisions/0016-account-type-intent-capture.md.
 */
final class AuthPayload extends Data
{
    public function __construct(
        public readonly User $user,
        public readonly string $token,
        public readonly UserCapabilities $capabilities,
        public readonly ?Provider $provider,
    ) {}
}
