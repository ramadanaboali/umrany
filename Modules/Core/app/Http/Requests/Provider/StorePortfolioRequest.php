<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests\Provider;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePortfolioRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'provider_category_id' => ['nullable', 'integer', Rule::exists('provider_categories', 'id')->whereNull('deleted_at')],
            // Uploaded files stored on the `public` disk (same convention as logo/cover) — see
            // docs/decisions/0030-provider-taxonomy-portfolio-and-admin-review.md.
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_IMAGE_KILOBYTES],
            // Plain URLs, not uploads — portfolio videos are hosted externally (e.g. YouTube).
            'videos' => ['nullable', 'array', 'max:10'],
            'videos.*' => ['url', 'max:2048'],
            'completion_date' => ['nullable', 'date'],
            'project_location' => ['nullable', 'string', 'max:255'],
        ];
    }
}
