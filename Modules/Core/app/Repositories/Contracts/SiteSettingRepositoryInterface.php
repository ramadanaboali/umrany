<?php

declare(strict_types=1);

namespace Modules\Core\Repositories\Contracts;

use Modules\Core\Models\SiteSetting;

interface SiteSettingRepositoryInterface
{
    public function current(): SiteSetting;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(SiteSetting $setting, array $attributes): SiteSetting;
}
