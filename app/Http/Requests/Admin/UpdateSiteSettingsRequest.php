<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Core\Enums\SocialPlatform;

final class UpdateSiteSettingsRequest extends FormRequest
{
    private const MAX_LOGO_KILOBYTES = 2048;

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
            'site_name' => ['required', 'string', 'max:255'],
            'site_title' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:'.self::MAX_LOGO_KILOBYTES],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'contact_address' => ['nullable', 'string', 'max:255'],
            'social_links' => ['sometimes', 'nullable', 'array'],
            'social_links.*' => ['nullable', 'url', 'max:255'],
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (ValidatorContract $validator): void {
                $unknown = array_diff(array_keys($this->input('social_links', [])), SocialPlatform::values());

                foreach ($unknown as $platform) {
                    $validator->errors()->add('social_links', __('admin.settings.unknown_social_platform', ['platform' => $platform]));
                }
            },
        ];
    }
}
