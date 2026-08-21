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

    /**
     * Regression test for docs/decisions/0014-user-soft-deletes-and-partial-unique-indexes.md's
     * extension to Country/Currency — a bare "soft-delete succeeds" test wouldn't catch a missing
     * or wrong partial index; this proves the `code` column is genuinely reusable after deletion.
     */
    public function test_soft_deleted_country_code_can_be_reused(): void
    {
        $country = Country::create(['code' => 'ZZ', 'name_en' => 'Zed', 'name_ar' => 'Zed', 'is_active' => true]);
        $country->delete();

        $this->assertSoftDeleted($country);

        Country::create(['code' => 'ZZ', 'name_en' => 'New Zed', 'name_ar' => 'New Zed', 'is_active' => true]);

        $this->assertDatabaseCount('countries', 2);
    }

    public function test_soft_deleted_currency_code_can_be_reused(): void
    {
        $currency = Currency::create(['code' => 'ZZZ', 'name_en' => 'Zed', 'name_ar' => 'Zed', 'symbol' => 'Z', 'is_active' => true]);
        $currency->delete();

        $this->assertSoftDeleted($currency);

        Currency::create(['code' => 'ZZZ', 'name_en' => 'New Zed', 'name_ar' => 'New Zed', 'symbol' => 'Z', 'is_active' => true]);

        $this->assertDatabaseCount('currencies', 2);
    }
}
