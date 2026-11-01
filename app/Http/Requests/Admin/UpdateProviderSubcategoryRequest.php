<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Models\ProviderCategory;
use Modules\Core\Models\ProviderSubcategory;

final class UpdateProviderSubcategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var ProviderCategory $category */
        $category = $this->route('providerCategory');
        /** @var ProviderSubcategory $subcategory */
        $subcategory = $this->route('providerSubcategory');

        return [
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('provider_subcategories', 'slug')->where('provider_category_id', $category->id)->whereNull('deleted_at')->ignore($subcategory->id)],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
