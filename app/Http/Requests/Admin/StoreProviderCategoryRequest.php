<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreProviderCategoryRequest extends FormRequest
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
        return [
            // whereNull('deleted_at'): a soft-deleted category's slug must be free to reuse — see
            // docs/decisions/0014-user-soft-deletes-and-partial-unique-indexes.md.
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('provider_categories', 'slug')->whereNull('deleted_at')],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
