<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Models\ProviderCategory;

final class UpdateProviderCategoryRequest extends FormRequest
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

        return [
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('provider_categories', 'slug')->whereNull('deleted_at')->ignore($category->id)],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
