<?php

declare(strict_types=1);

namespace Modules\Core\Repositories;

use App\Models\User;
use Modules\Core\Enums\VerificationCodePurpose;
use Modules\Core\Enums\VerificationCodeType;
use Modules\Core\Models\VerificationCode;
use Modules\Core\Repositories\Contracts\VerificationCodeRepositoryInterface;

final class EloquentVerificationCodeRepository implements VerificationCodeRepositoryInterface
{
    public function findUsable(User $user, VerificationCodeType $type, VerificationCodePurpose $purpose): ?VerificationCode
    {
        return VerificationCode::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->where('purpose', $purpose)
            ->usable()
            ->latest('id')
            ->first();
    }

    public function invalidateUsable(User $user, VerificationCodeType $type, VerificationCodePurpose $purpose): void
    {
        VerificationCode::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->where('purpose', $purpose)
            ->usable()
            ->update(['consumed_at' => now()]);
    }

    public function create(array $attributes): VerificationCode
    {
        return VerificationCode::create($attributes);
    }

    public function incrementAttempts(VerificationCode $code): void
    {
        $code->increment('attempts');
    }

    public function markConsumed(VerificationCode $code): void
    {
        $code->forceFill(['consumed_at' => now()])->save();
    }
}
