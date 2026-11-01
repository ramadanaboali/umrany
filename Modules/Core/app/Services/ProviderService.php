<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Enums\NotificationEvent;
use Modules\Core\Enums\ProviderStatus;
use Modules\Core\Enums\ProviderVerificationStatus;
use Modules\Core\Events\UserCapabilitiesChanged;
use Modules\Core\Models\Provider;
use Modules\Core\Models\ProviderCertificate;
use Modules\Core\Models\ProviderPortfolio;
use Modules\Core\Models\ProviderVerification;
use Modules\Core\Repositories\Contracts\ProviderRepositoryInterface;

final class ProviderService
{
    private const DOCUMENT_DISK = 'public';

    private const MEDIA_DISK = 'public';

    public function __construct(
        private readonly ProviderRepositoryInterface $providers,
        private readonly NotificationDispatchService $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function activate(User $user, array $data): Provider
    {
        return DB::transaction(function () use ($user, $data) {
            $categoryIds = $data['category_ids'] ?? [];
            $subcategoryIds = $data['subcategory_ids'] ?? [];
            unset($data['category_ids'], $data['subcategory_ids']);

            $provider = $this->providers->create([
                ...$data,
                'user_id' => $user->id,
                'status' => ProviderStatus::PendingReview,
                'has_ecommerce_access' => false,
                'has_erp_access' => false,
            ]);

            // FR-PROVIDER-003: a provider profile may offer multiple business categories at once —
            // sync() inside the same transaction as the provider row itself. Subcategories are
            // always optional (docs/decisions/0032-provider-subcategories.md).
            $provider->categories()->sync($categoryIds);
            $provider->subcategories()->sync($subcategoryIds);

            $provider->verification()->create(['status' => ProviderVerificationStatus::NotSubmitted]);

            event(new UserCapabilitiesChanged($user->id));

            return $provider->load(['verification', 'categories', 'subcategories']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Provider $provider, array $data): Provider
    {
        return DB::transaction(function () use ($provider, $data) {
            if (array_key_exists('category_ids', $data)) {
                $provider->categories()->sync($data['category_ids']);
                unset($data['category_ids']);
            }

            if (array_key_exists('subcategory_ids', $data)) {
                $provider->subcategories()->sync($data['subcategory_ids'] ?? []);
                unset($data['subcategory_ids']);
            }

            $updated = $this->providers->update($provider, $data);

            event(new UserCapabilitiesChanged($provider->user_id));

            return $updated->load(['verification', 'categories', 'subcategories']);
        });
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

            // Self-triggered — explicit $locale (the current request's own locale), same rule as
            // RegistrationCompleted. See docs/decisions/0029-centralized-notification-service-and-
            // api-locale.md.
            $this->notifications->send($provider->user, NotificationEvent::ProviderVerificationSubmitted, [], null, app()->getLocale());

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

    /**
     * FR-PROVIDER-006. Images are uploaded files stored under
     * providers/{id}/portfolio/{portfolioId} on the `public` disk (same convention as logo/cover);
     * videos are a plain JSON array of URLs, never uploads.
     *
     * @param  array<string, mixed>  $data
     */
    public function createPortfolio(Provider $provider, array $data): ProviderPortfolio
    {
        return DB::transaction(function () use ($provider, $data) {
            $portfolio = $provider->portfolios()->create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'provider_category_id' => $data['provider_category_id'] ?? null,
                'videos' => $data['videos'] ?? null,
                'completion_date' => $data['completion_date'] ?? null,
                'project_location' => $data['project_location'] ?? null,
            ]);

            if (! empty($data['images'])) {
                $portfolio->forceFill(['images' => $this->storePortfolioImages($provider, $portfolio, $data['images'])])->save();
            }

            return $portfolio->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePortfolio(Provider $provider, ProviderPortfolio $portfolio, array $data): ProviderPortfolio
    {
        $this->assertOwnership($provider, $portfolio);

        // New images are stored, and the DB row committed, before any old image is deleted — a
        // failed update leaves at worst an orphaned upload, never a row referencing files that
        // were already deleted out from under it.
        $oldImages = $portfolio->images ?? [];
        $replacingImages = array_key_exists('images', $data);

        if ($replacingImages) {
            $data['images'] = ! empty($data['images']) ? $this->storePortfolioImages($provider, $portfolio, $data['images']) : null;
        }

        $portfolio = DB::transaction(function () use ($portfolio, $data) {
            $portfolio->update($data);

            return $portfolio->refresh();
        });

        if ($replacingImages) {
            $this->deleteMediaFiles($oldImages);
        }

        return $portfolio;
    }

    public function deletePortfolio(Provider $provider, ProviderPortfolio $portfolio): void
    {
        $this->assertOwnership($provider, $portfolio);

        $images = $portfolio->images ?? [];
        $portfolio->delete();
        $this->deleteMediaFiles($images);
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, string>
     */
    private function storePortfolioImages(Provider $provider, ProviderPortfolio $portfolio, array $files): array
    {
        return array_map(
            fn (UploadedFile $file) => $file->store("providers/{$provider->id}/portfolio/{$portfolio->id}", self::MEDIA_DISK),
            $files,
        );
    }

    /**
     * FR-PROVIDER-007.
     *
     * @param  array<string, mixed>  $data
     */
    public function createCertificate(Provider $provider, array $data, ?UploadedFile $file = null): ProviderCertificate
    {
        return DB::transaction(function () use ($provider, $data, $file) {
            $certificate = $provider->certificates()->create([
                'name' => $data['name'],
                'issuer' => $data['issuer'] ?? null,
                'issued_date' => $data['issued_date'] ?? null,
            ]);

            if ($file !== null) {
                $path = $file->store("providers/{$provider->id}/certificates/{$certificate->id}", self::MEDIA_DISK);
                $certificate->forceFill(['file_path' => $path])->save();
            }

            return $certificate->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCertificate(Provider $provider, ProviderCertificate $certificate, array $data, ?UploadedFile $file = null): ProviderCertificate
    {
        $this->assertOwnership($provider, $certificate);

        unset($data['file']);

        // Same ordering as updatePortfolio(): store the new file and commit the DB row first,
        // delete the old file only after that succeeds.
        $oldFilePath = $certificate->file_path;
        $replacingFile = $file !== null;

        if ($replacingFile) {
            $data['file_path'] = $file->store("providers/{$provider->id}/certificates/{$certificate->id}", self::MEDIA_DISK);
        }

        $certificate = DB::transaction(function () use ($certificate, $data) {
            $certificate->update($data);

            return $certificate->refresh();
        });

        if ($replacingFile) {
            $this->deleteMediaFiles(array_filter([$oldFilePath]));
        }

        return $certificate;
    }

    public function deleteCertificate(Provider $provider, ProviderCertificate $certificate): void
    {
        $this->assertOwnership($provider, $certificate);

        $filePath = $certificate->file_path;
        $certificate->delete();
        $this->deleteMediaFiles(array_filter([$filePath]));
    }

    private function deleteExistingMedia(?string $path): void
    {
        if ($path !== null && Storage::disk(self::MEDIA_DISK)->exists($path)) {
            Storage::disk(self::MEDIA_DISK)->delete($path);
        }
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function deleteMediaFiles(array $paths): void
    {
        foreach ($paths as $path) {
            $this->deleteExistingMedia($path);
        }
    }

    /**
     * A portfolio/certificate item can only ever be mutated by the provider that owns it —
     * 404s (never leaks whether the item exists under a different provider) instead of silently
     * operating on someone else's record.
     */
    private function assertOwnership(Provider $provider, Model $item): void
    {
        if ($item->getAttribute('provider_id') !== $provider->id) {
            throw (new ModelNotFoundException)->setModel($item::class);
        }
    }
}
