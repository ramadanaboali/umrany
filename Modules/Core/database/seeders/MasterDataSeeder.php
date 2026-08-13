<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\City;
use Modules\Core\Models\Country;
use Modules\Core\Models\Currency;

/**
 * A small curated starter set, not a full ISO country/currency import — enough for the platform
 * to be usable in its primary market (Saudi Arabia) plus its immediate GCC neighbors, per
 * docs/business/overview.md's Middle East focus. Admins can add more through the data directly;
 * a bulk-import tool is out of scope for this pass.
 */
final class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $saudi = Country::query()->updateOrCreate(
            ['code' => 'SA'],
            ['name_en' => 'Saudi Arabia', 'name_ar' => 'المملكة العربية السعودية', 'phone_code' => '+966', 'is_active' => true],
        );

        $uae = Country::query()->updateOrCreate(
            ['code' => 'AE'],
            ['name_en' => 'United Arab Emirates', 'name_ar' => 'الإمارات العربية المتحدة', 'phone_code' => '+971', 'is_active' => true],
        );

        $egypt = Country::query()->updateOrCreate(
            ['code' => 'EG'],
            ['name_en' => 'Egypt', 'name_ar' => 'مصر', 'phone_code' => '+20', 'is_active' => true],
        );

        $cities = [
            $saudi->id => [
                ['name_en' => 'Riyadh', 'name_ar' => 'الرياض'],
                ['name_en' => 'Jeddah', 'name_ar' => 'جدة'],
                ['name_en' => 'Dammam', 'name_ar' => 'الدمام'],
            ],
            $uae->id => [
                ['name_en' => 'Dubai', 'name_ar' => 'دبي'],
                ['name_en' => 'Abu Dhabi', 'name_ar' => 'أبو ظبي'],
            ],
            $egypt->id => [
                ['name_en' => 'Cairo', 'name_ar' => 'القاهرة'],
                ['name_en' => 'Alexandria', 'name_ar' => 'الإسكندرية'],
            ],
        ];

        foreach ($cities as $countryId => $countryCities) {
            foreach ($countryCities as $city) {
                City::query()->updateOrCreate(
                    ['country_id' => $countryId, 'name_en' => $city['name_en']],
                    ['name_ar' => $city['name_ar'], 'is_active' => true],
                );
            }
        }

        $currencies = [
            ['code' => 'SAR', 'name_en' => 'Saudi Riyal', 'name_ar' => 'ريال سعودي', 'symbol' => 'SAR'],
            ['code' => 'AED', 'name_en' => 'UAE Dirham', 'name_ar' => 'درهم إماراتي', 'symbol' => 'AED'],
            ['code' => 'EGP', 'name_en' => 'Egyptian Pound', 'name_ar' => 'جنيه مصري', 'symbol' => 'EGP'],
            ['code' => 'USD', 'name_en' => 'US Dollar', 'name_ar' => 'دولار أمريكي', 'symbol' => '$'],
        ];

        foreach ($currencies as $currency) {
            Currency::query()->updateOrCreate(
                ['code' => $currency['code']],
                [...$currency, 'is_active' => true],
            );
        }
    }
}
