<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\ProviderCategory;
use Modules\Core\Models\ProviderSubcategory;

/**
 * FR-PROVIDER-003's subcategory layer — unlike the 10 top-level categories (named explicitly in
 * the source spec), no subcategory list is given there. This is a reasonable, synthetic
 * construction-industry starter set, not sourced from the spec — see
 * docs/decisions/0032-provider-subcategories.md. Idempotent (updateOrCreate keyed on
 * category slug + subcategory slug) — see root CLAUDE.md Rule 6. Must run after
 * ProviderCategorySeeder (CoreDatabaseSeeder's ordering).
 */
final class ProviderSubcategorySeeder extends Seeder
{
    public function run(): void
    {
        $subcategoriesByCategorySlug = [
            'contractor' => [
                ['slug' => 'general-contracting', 'name_en' => 'General Contracting', 'name_ar' => 'مقاولات عامة'],
                ['slug' => 'residential-construction', 'name_en' => 'Residential Construction', 'name_ar' => 'إنشاءات سكنية'],
                ['slug' => 'commercial-construction', 'name_en' => 'Commercial Construction', 'name_ar' => 'إنشاءات تجارية'],
                ['slug' => 'infrastructure-construction', 'name_en' => 'Infrastructure Construction', 'name_ar' => 'إنشاءات البنية التحتية'],
            ],
            'supplier' => [
                ['slug' => 'building-materials', 'name_en' => 'Building Materials', 'name_ar' => 'مواد بناء'],
                ['slug' => 'electrical-supplies', 'name_en' => 'Electrical Supplies', 'name_ar' => 'مستلزمات كهربائية'],
                ['slug' => 'plumbing-supplies', 'name_en' => 'Plumbing Supplies', 'name_ar' => 'مستلزمات سباكة'],
                ['slug' => 'finishing-materials', 'name_en' => 'Finishing Materials', 'name_ar' => 'مواد تشطيب'],
            ],
            'engineering-office' => [
                ['slug' => 'architectural-design', 'name_en' => 'Architectural Design', 'name_ar' => 'تصميم معماري'],
                ['slug' => 'structural-engineering', 'name_en' => 'Structural Engineering', 'name_ar' => 'هندسة إنشائية'],
                ['slug' => 'mep-engineering', 'name_en' => 'MEP Engineering', 'name_ar' => 'الهندسة الكهروميكانيكية'],
                ['slug' => 'surveying-services', 'name_en' => 'Surveying Services', 'name_ar' => 'خدمات المساحة'],
            ],
            'consultant' => [
                ['slug' => 'project-management-consultancy', 'name_en' => 'Project Management Consultancy', 'name_ar' => 'استشارات إدارة المشاريع'],
                ['slug' => 'cost-consultancy', 'name_en' => 'Cost Consultancy', 'name_ar' => 'استشارات التكلفة'],
                ['slug' => 'safety-consultancy', 'name_en' => 'Safety Consultancy', 'name_ar' => 'استشارات السلامة'],
            ],
            'manufacturer' => [
                ['slug' => 'concrete-products', 'name_en' => 'Concrete Products', 'name_ar' => 'منتجات خرسانية'],
                ['slug' => 'steel-products', 'name_en' => 'Steel Products', 'name_ar' => 'منتجات معدنية'],
                ['slug' => 'aluminum-glass-products', 'name_en' => 'Aluminum & Glass Products', 'name_ar' => 'منتجات الألمنيوم والزجاج'],
            ],
            'interior-design' => [
                ['slug' => 'residential-interior-design', 'name_en' => 'Residential Interior Design', 'name_ar' => 'تصميم داخلي سكني'],
                ['slug' => 'commercial-interior-design', 'name_en' => 'Commercial Interior Design', 'name_ar' => 'تصميم داخلي تجاري'],
                ['slug' => 'furniture-fit-out', 'name_en' => 'Furniture & Fit-Out', 'name_ar' => 'الأثاث والتجهيزات'],
            ],
            'landscape' => [
                ['slug' => 'garden-design', 'name_en' => 'Garden Design', 'name_ar' => 'تصميم الحدائق'],
                ['slug' => 'irrigation-systems', 'name_en' => 'Irrigation Systems', 'name_ar' => 'أنظمة الري'],
                ['slug' => 'hardscaping', 'name_en' => 'Hardscaping', 'name_ar' => 'الأعمال الصخرية والممرات'],
            ],
            'maintenance' => [
                ['slug' => 'general-building-maintenance', 'name_en' => 'General Building Maintenance', 'name_ar' => 'صيانة عامة للمباني'],
                ['slug' => 'hvac-maintenance', 'name_en' => 'HVAC Maintenance', 'name_ar' => 'صيانة أنظمة التكييف'],
                ['slug' => 'electrical-maintenance', 'name_en' => 'Electrical Maintenance', 'name_ar' => 'صيانة كهربائية'],
                ['slug' => 'plumbing-maintenance', 'name_en' => 'Plumbing Maintenance', 'name_ar' => 'صيانة سباكة'],
            ],
            'smart-home' => [
                ['slug' => 'home-automation', 'name_en' => 'Home Automation', 'name_ar' => 'أتمتة المنازل'],
                ['slug' => 'security-systems', 'name_en' => 'Security Systems', 'name_ar' => 'أنظمة الأمان'],
                ['slug' => 'smart-lighting', 'name_en' => 'Smart Lighting', 'name_ar' => 'الإضاءة الذكية'],
            ],
            'steel-structure' => [
                ['slug' => 'structural-steel-fabrication', 'name_en' => 'Structural Steel Fabrication', 'name_ar' => 'تصنيع الهياكل الفولاذية'],
                ['slug' => 'steel-erection', 'name_en' => 'Steel Erection', 'name_ar' => 'تركيب الهياكل الفولاذية'],
                ['slug' => 'metal-cladding', 'name_en' => 'Metal Cladding', 'name_ar' => 'التكسية المعدنية'],
            ],
        ];

        foreach ($subcategoriesByCategorySlug as $categorySlug => $subcategories) {
            $category = ProviderCategory::query()->where('slug', $categorySlug)->first();

            if ($category === null) {
                continue;
            }

            foreach ($subcategories as $index => $subcategory) {
                ProviderSubcategory::query()->updateOrCreate(
                    ['provider_category_id' => $category->id, 'slug' => $subcategory['slug']],
                    [...$subcategory, 'display_order' => $index + 1],
                );
            }
        }
    }
}
