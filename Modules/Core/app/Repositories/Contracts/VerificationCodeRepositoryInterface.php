<?php

declare(strict_types=1);

namespace Modules\Core\Repositories\Contracts;

use App\Models\User;
use Modules\Core\Enums\VerificationCodePurpose;
use Modules\Core\Enums\VerificationCodeType;
use Modules\Core\Models\VerificationCode;

interface VerificationCodeRepositoryInterface
{
    public function findUsable(User $user, VerificationCodeType $type, VerificationCodePurpose $purpose): ?VerificationCode;

    /**
     * Invalidates any still-usable prior code of the same (channel, purpose) pair.
     */
    public function invalidateUsable(User $user, VerificationCodeType $type, VerificationCodePurpose $purpose): void;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): VerificationCode;

    public function incrementAttempts(VerificationCode $code): void;

    public function markConsumed(VerificationCode $code): void;
}
