<?php

declare(strict_types=1);

namespace Modules\Core\Repositories;

use Modules\Core\Models\SiteSetting;
use Modules\Core\Repositories\Contracts\SiteSettingRepositoryInterface;

final class EloquentSiteSettingRepository implements SiteSettingRepositoryInterface
{
    public function current(): SiteSetting
    {
        return SiteSetting::current();
    }

    public function update(SiteSetting $setting, array $attributes): SiteSetting
    {
        $setting->update($attributes);

        return $setting;
    }
}
