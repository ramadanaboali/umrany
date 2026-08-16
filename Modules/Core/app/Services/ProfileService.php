<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Enums\VerificationCodePurpose;
use Modules\Core\Enums\VerificationCodeType;
use Modules\Core\Models\UserProfile;
use Modules\Core\Repositories\Contracts\UserProfileRepositoryInterface;
use Modules\Core\Repositories\Contracts\UserRepositoryInterface;
use Modules\Core\Support\PhoneNumber;

final class ProfileService
{
    private const AVATAR_DISK = 'public';

    private const PROFILE_FIELDS = ['full_name', 'address', 'country_id', 'city_id'];

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly UserProfileRepositoryInterface $profiles,
        private readonly VerificationCodeService $verificationCodes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateProfile(User $user, array $data): User
    {
        // FR-PROFILE-002: changing email/mobile requires re-verification — clear the old
        // verified-at timestamp and issue a fresh code rather than silently trusting the new
        // value.
        if (array_key_exists('email', $data) && $data['email'] !== $user->email) {
            $this->users->forceUpdate($user, ['email' => $data['email'], 'email_verified_at' => null]);
            $this->verificationCodes->issue($user, VerificationCodeType::Email, VerificationCodePurpose::AccountVerification);
        }

        if (array_key_exists('mobile', $data)) {
            $normalizedMobile = PhoneNumber::normalize($data['mobile']);

            if ($normalizedMobile !== $user->mobile) {
                $this->users->forceUpdate($user, ['mobile' => $normalizedMobile, 'mobile_verified_at' => null]);
                $this->verificationCodes->issue($user, VerificationCodeType::Mobile, VerificationCodePurpose::AccountVerification);
            }
        }

        $profileData = array_intersect_key($data, array_flip(self::PROFILE_FIELDS));

        if ($profileData !== []) {
            $profile = $this->profiles->findByUserId($user->id);
            if ($profile) {
                $this->profiles->update($profile, $profileData);
            }
        }

        return $user->refresh();
    }

    public function updateAvatar(User $user, UploadedFile $file): string
    {
        $profile = $this->profiles->findByUserId($user->id);
        $this->deleteExistingAvatar($profile?->avatar_path);

        $path = $file->store('avatars/users', self::AVATAR_DISK);

        if ($profile) {
            $this->profiles->update($profile, ['avatar_path' => $path]);
        }

        return $path;
    }

    public function removeAvatar(User $user): void
    {
        $profile = $this->profiles->findByUserId($user->id);
        $this->deleteExistingAvatar($profile?->avatar_path);

        if ($profile) {
            $this->profiles->update($profile, ['avatar_path' => null]);
        }
    }

    public function updateLanguage(User $user, string $language): void
    {
        $profile = $this->profiles->findByUserId($user->id);
        if ($profile instanceof UserProfile) {
            $this->profiles->update($profile, ['preferred_language' => $language]);
        }
    }

    public function updateCurrency(User $user, int $currencyId): void
    {
        $profile = $this->profiles->findByUserId($user->id);
        if ($profile instanceof UserProfile) {
            $this->profiles->update($profile, ['preferred_currency_id' => $currencyId]);
        }
    }

    private function deleteExistingAvatar(?string $path): void
    {
        if ($path !== null && Storage::disk(self::AVATAR_DISK)->exists($path)) {
            Storage::disk(self::AVATAR_DISK)->delete($path);
        }
    }
}
