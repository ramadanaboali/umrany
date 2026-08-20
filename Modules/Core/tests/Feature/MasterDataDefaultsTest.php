<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Database\QueryException;
use Modules\Core\Database\Seeders\MasterDataSeeder;
use Modules\Core\Models\Country;
use Modules\Core\Models\Currency;
use Tests\TestCase;

/**
 * Covers Country/Currency::default() moving from a config('core.defaults.*') lookup to a real
 * `is_default` DB flag, and the partial unique index that enforces at most one default row per
 * table — see docs/decisions/0020-database-backed-platform-defaults.md.
 */
class MasterDataDefaultsTest extends TestCase
{
    public function test_seeded_country_default_resolves_from_the_is_default_flag(): void
    {
        $this->seed(MasterDataSeeder::class);

        $default = Country::default();

        $this->assertNotNull($default);
        $this->assertSame('SA', $default->code);
    }

    public function test_seeded_currency_default_resolves_from_the_is_default_flag(): void
    {
        $this->seed(MasterDataSeeder::class);

        $default = Currency::default();

        $this->assertNotNull($default);
        $this->assertSame('SAR', $default->code);
    }

    public function test_only_one_default_country_is_allowed_at_the_database_level(): void
    {
        Country::create([
            'code' => 'AA', 'name_en' => 'A', 'name_ar' => 'A', 'is_active' => true, 'is_default' => true,
        ]);

        $this->expectException(QueryException::class);

        Country::create([
            'code' => 'BB', 'name_en' => 'B', 'name_ar' => 'B', 'is_active' => true, 'is_default' => true,
        ]);
    }

    public function test_only_one_default_currency_is_allowed_at_the_database_level(): void
    {
        Currency::create([
            'code' => 'AAA', 'name_en' => 'A', 'name_ar' => 'A', 'symbol' => 'A', 'is_active' => true, 'is_default' => true,
        ]);

        $this->expectException(QueryException::class);

        Currency::create([
            'code' => 'BBB', 'name_en' => 'B', 'name_ar' => 'B', 'symbol' => 'B', 'is_active' => true, 'is_default' => true,
        ]);
    }
}
