<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests\Provider;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePortfolioRequest extends FormRequest
{
    private const MAX_IMAGE_KILOBYTES = 5120;

    public function authorize(): bool
    {
        return $this->user()->provider !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'provider_category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('provider_categories', 'id')->whereNull('deleted_at')],
            'images' => ['sometimes', 'nullable', 'array', 'max:10'],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_IMAGE_KILOBYTES],
            'videos' => ['sometimes', 'nullable', 'array', 'max:10'],
            'videos.*' => ['url', 'max:2048'],
            'completion_date' => ['sometimes', 'nullable', 'date'],
            'project_location' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
