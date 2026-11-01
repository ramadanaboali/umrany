<?php

declare(strict_types=1);

namespace Modules\Core\Data;

/**
 * The outcome of one Modules\Core\Contracts\GovernmentVerificationClient::check() call — either
 * $data is present (success) or $failureReason is (the check itself failed/was unavailable,
 * distinct from the CR number simply being invalid, which the client should also report via
 * $failureReason since there is no separate "not found" case in the source spec).
 */
final class GovernmentVerificationResult
{
    private function __construct(
        public readonly bool $succeeded,
        public readonly ?GovernmentBusinessData $data,
        public readonly ?string $failureReason,
    ) {}

    public static function success(GovernmentBusinessData $data): self
    {
        return new self(true, $data, null);
    }

    public static function failure(string $reason): self
    {
        return new self(false, null, $reason);
    }
}
