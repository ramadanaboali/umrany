<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\ProviderCategory;

/**
 * FR-PROVIDER-003's fixed starter category list — the exact 10 named in
 * docs/decisions/0030-provider-taxonomy-portfolio-and-admin-review.md. Idempotent
 * (updateOrCreate keyed on slug) — see root CLAUDE.md Rule 6.
 */
final class ProviderCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['slug' => 'contractor', 'name_en' => 'Contractor', 'name_ar' => 'مقاول', 'display_order' => 1],
            ['slug' => 'supplier', 'name_en' => 'Supplier', 'name_ar' => 'مورد', 'display_order' => 2],
            ['slug' => 'engineering-office', 'name_en' => 'Engineering Office', 'name_ar' => 'مكتب هندسي', 'display_order' => 3],
            ['slug' => 'consultant', 'name_en' => 'Consultant', 'name_ar' => 'مستشار', 'display_order' => 4],
            ['slug' => 'manufacturer', 'name_en' => 'Manufacturer', 'name_ar' => 'مصنّع', 'display_order' => 5],
            ['slug' => 'interior-design', 'name_en' => 'Interior Design', 'name_ar' => 'تصميم داخلي', 'display_order' => 6],
            ['slug' => 'landscape', 'name_en' => 'Landscape', 'name_ar' => 'تنسيق الحدائق', 'display_order' => 7],
            ['slug' => 'maintenance', 'name_en' => 'Maintenance', 'name_ar' => 'الصيانة', 'display_order' => 8],
            ['slug' => 'smart-home', 'name_en' => 'Smart Home', 'name_ar' => 'المنزل الذكي', 'display_order' => 9],
            ['slug' => 'steel-structure', 'name_en' => 'Steel Structure', 'name_ar' => 'الهياكل الفولاذية', 'display_order' => 10],
        ];

        foreach ($categories as $category) {
            ProviderCategory::query()->updateOrCreate(
                ['slug' => $category['slug']],
                $category,
            );
        }
    }
}
