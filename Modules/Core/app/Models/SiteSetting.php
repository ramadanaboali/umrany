<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Platform-wide branding/contact/social config — a single row (id=1, guaranteed by
 * SiteSettingSeeder). Deliberately distinct from the future, broader `SystemSetting` entity
 * (business config — commission, gateway fees — still not built, see Modules/Core/CLAUDE.md).
 */
#[Fillable(['site_name', 'site_title', 'logo_path', 'contact_email', 'contact_phone', 'contact_address', 'social_links'])]
class SiteSetting extends Model
{
    protected function casts(): array
    {
        return [
            'social_links' => 'array',
        ];
    }

    /**
     * The single row every screen/endpoint reads/writes — created idempotently even before a
     * seeder runs, so this is never null.
     */
    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1], ['site_name' => config('app.name')]);
    }
}
