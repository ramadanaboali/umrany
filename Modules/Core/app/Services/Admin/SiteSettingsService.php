<?php

declare(strict_types=1);

namespace Modules\Core\Services\Admin;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\SiteSetting;
use Modules\Core\Repositories\Contracts\SiteSettingRepositoryInterface;

final class SiteSettingsService
{
    private const MEDIA_DISK = 'public';

    public function __construct(
        private readonly SiteSettingRepositoryInterface $settings,
    ) {}

    public function current(): SiteSetting
    {
        return $this->settings->current();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SiteSetting $setting, array $data): SiteSetting
    {
        return $this->settings->update($setting, $data);
    }

    /**
     * Mirrors Modules\Core\Services\ProviderService::updateLogo()'s store/delete-existing
     * pattern exactly — same disk, same "delete before replace" order.
     */
    public function updateLogo(SiteSetting $setting, UploadedFile $file): SiteSetting
    {
        $this->deleteExistingLogo($setting->logo_path);

        $path = $file->store('site-settings/logo', self::MEDIA_DISK);

        return $this->settings->update($setting, ['logo_path' => $path]);
    }

    public function removeLogo(SiteSetting $setting): SiteSetting
    {
        $this->deleteExistingLogo($setting->logo_path);

        return $this->settings->update($setting, ['logo_path' => null]);
    }

    private function deleteExistingLogo(?string $path): void
    {
        if ($path !== null && Storage::disk(self::MEDIA_DISK)->exists($path)) {
            Storage::disk(self::MEDIA_DISK)->delete($path);
        }
    }
}
