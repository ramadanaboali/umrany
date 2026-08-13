<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Enums\VerificationCodePurpose;
use Modules\Core\Enums\VerificationCodeType;
use Modules\Core\Models\VerificationCode;
use Modules\Core\Notifications\VerificationCodeNotification;
use Modules\Core\Repositories\Contracts\VerificationCodeRepositoryInterface;

/**
 * The single OTP mechanism backing both account verification and password reset — see
 * docs/architecture/backend-layering.md and docs/modules/core.md § Auth for why `type` (delivery
 * channel) and `purpose` (what the code authorizes) are separate axes.
 */
final class VerificationCodeService
{
    public const TTL_MINUTES = 10;

    private const CODE_MIN = 100000;

    private const CODE_MAX = 999999;

    /**
     * A code stops being guessable after this many wrong attempts, even before it expires.
     */
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly VerificationCodeRepositoryInterface $verificationCodes,
    ) {}

    public function issue(User $user, VerificationCodeType $type, VerificationCodePurpose $purpose): VerificationCode
    {
        $this->verificationCodes->invalidateUsable($user, $type, $purpose);

        $plainCode = (string) random_int(self::CODE_MIN, self::CODE_MAX);

        $code = $this->verificationCodes->create([
            'user_id' => $user->id,
            'type' => $type,
            'purpose' => $purpose,
            'code_hash' => Hash::make($plainCode),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        $user->notify(new VerificationCodeNotification($plainCode, $type, $purpose, self::TTL_MINUTES));

        return $code;
    }

    public function consume(User $user, VerificationCodeType $type, VerificationCodePurpose $purpose, string $plainCode): bool
    {
        $code = $this->verificationCodes->findUsable($user, $type, $purpose);

        if (! $code || $code->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (! Hash::check($plainCode, $code->code_hash)) {
            $this->verificationCodes->incrementAttempts($code);

            return false;
        }

        $this->verificationCodes->markConsumed($code);

        return true;
    }
}
