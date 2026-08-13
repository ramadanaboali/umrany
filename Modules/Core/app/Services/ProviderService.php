<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Enums\ProviderStatus;
use Modules\Core\Enums\ProviderVerificationStatus;
use Modules\Core\Events\UserCapabilitiesChanged;
use Modules\Core\Models\Provider;
use Modules\Core\Models\ProviderVerification;
use Modules\Core\Repositories\Contracts\ProviderRepositoryInterface;

final class ProviderService
{
    private const DOCUMENT_DISK = 'public';

    private const MEDIA_DISK = 'public';

    public function __construct(
        private readonly ProviderRepositoryInterface $providers,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function activate(User $user, array $data): Provider
    {
        return DB::transaction(function () use ($user, $data) {
            $provider = $this->providers->create([
                ...$data,
                'user_id' => $user->id,
                'status' => ProviderStatus::PendingReview,
                'has_ecommerce_access' => false,
                'has_erp_access' => false,
            ]);

            $provider->verification()->create(['status' => ProviderVerificationStatus::NotSubmitted]);

            event(new UserCapabilitiesChanged($user->id));

            return $provider->load('verification');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Provider $provider, array $data): Provider
    {
        $updated = $this->providers->update($provider, $data);

        event(new UserCapabilitiesChanged($provider->user_id));

        return $updated->load('verification');
    }

    /**
     * @param  array<int, array{type: string, file: UploadedFile}>  $documents
     */
    public function submitVerification(Provider $provider, array $documents): ProviderVerification
    {
        return DB::transaction(function () use ($provider, $documents) {
            foreach ($documents as $document) {
                $path = $document['file']->store("providers/{$provider->id}/documents", self::DOCUMENT_DISK);

                $provider->documents()->create([
                    'type' => $document['type'],
                    'file_path' => $path,
                    'original_name' => $document['file']->getClientOriginalName(),
                    'uploaded_at' => now(),
                ]);
            }

            $verification = $provider->verification ?? $provider->verification()->create();

            $verification->forceFill([
                'status' => ProviderVerificationStatus::Pending,
                'submitted_at' => now(),
                'reviewed_at' => null,
                'reviewed_by' => null,
                'rejection_reason' => null,
            ])->save();

            event(new UserCapabilitiesChanged($provider->user_id));

            return $verification->refresh();
        });
    }

    public function updateLogo(Provider $provider, UploadedFile $file): string
    {
        $this->deleteExistingMedia($provider->logo_path);

        $path = $file->store("providers/{$provider->id}/logo", self::MEDIA_DISK);
        $this->providers->update($provider, ['logo_path' => $path]);

        return $path;
    }

    public function removeLogo(Provider $provider): void
    {
        $this->deleteExistingMedia($provider->logo_path);
        $this->providers->update($provider, ['logo_path' => null]);
    }

    public function updateCover(Provider $provider, UploadedFile $file): string
    {
        $this->deleteExistingMedia($provider->cover_path);

        $path = $file->store("providers/{$provider->id}/cover", self::MEDIA_DISK);
        $this->providers->update($provider, ['cover_path' => $path]);

        return $path;
    }

    public function removeCover(Provider $provider): void
    {
        $this->deleteExistingMedia($provider->cover_path);
        $this->providers->update($provider, ['cover_path' => null]);
    }

    private function deleteExistingMedia(?string $path): void
    {
        if ($path !== null && Storage::disk(self::MEDIA_DISK)->exists($path)) {
            Storage::disk(self::MEDIA_DISK)->delete($path);
        }
    }
}
