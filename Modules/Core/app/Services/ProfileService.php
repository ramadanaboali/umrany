<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncoderInterface;
use Modules\Core\Enums\VerificationCodePurpose;
use Modules\Core\Enums\VerificationCodeType;
use Modules\Core\Models\UserProfile;
use Modules\Core\Repositories\Contracts\UserProfileRepositoryInterface;
use Modules\Core\Repositories\Contracts\UserRepositoryInterface;
use Modules\Core\Support\PhoneNumber;

final class ProfileService
{
    private const PROFILE_FIELDS = ['full_name', 'address', 'country_id', 'city_id'];

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly UserProfileRepositoryInterface $profiles,
        private readonly VerificationCodeService $verificationCodes,
        private readonly ImageManager $images,
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

    /**
     * Every upload is re-encoded to config('core.avatar.*')'s format/dimensions/quality
     * regardless of what was submitted (FR-PROFILE-003 "images shall be automatically
     * optimized") — a useful side effect of the re-encode is that EXIF data (including GPS
     * coordinates a phone photo may carry) is stripped along the way.
     */
    public function updateAvatar(User $user, UploadedFile $file): string
    {
        $profile = $this->profiles->findByUserId($user->id);
        $this->deleteExistingAvatar($profile?->avatar_path);

        $format = (string) config('core.avatar.format');

        $encoded = $this->images
            ->decodePath($file->getRealPath())
            ->cover((int) config('core.avatar.width'), (int) config('core.avatar.height'))
            ->encode($this->encoderFor($format));

        $path = config('core.avatar.path').'/'.Str::uuid().'.'.$format;
        Storage::disk(config('core.avatar.disk'))->put($path, (string) $encoded);

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

    /**
     * `core.avatar.format` genuinely drives the stored encoding/extension — not just a config
     * value nobody reads. `webp` is the default (best size/quality tradeoff of the three); `jpeg`/
     * `png` exist for a deploy that needs broader legacy-client image support.
     */
    private function encoderFor(string $format): EncoderInterface
    {
        $quality = (int) config('core.avatar.quality');

        return match ($format) {
            'jpeg', 'jpg' => new JpegEncoder(quality: $quality),
            'png' => new PngEncoder,
            default => new WebpEncoder(quality: $quality),
        };
    }

    private function deleteExistingAvatar(?string $path): void
    {
        $disk = config('core.avatar.disk');

        if ($path !== null && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }
}
