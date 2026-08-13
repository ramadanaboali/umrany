<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests\Provider;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Enums\SocialPlatform;

final class UpdateProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->provider !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $providerId = $this->user()->provider?->id;

        return [
            'company_name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'country_id' => ['sometimes', 'nullable', 'exists:countries,id'],
            'city_id' => ['sometimes', 'nullable', 'exists:cities,id'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'commercial_registration_number' => ['sometimes', 'nullable', 'string', 'max:64', Rule::unique('providers', 'commercial_registration_number')->ignore($providerId)],
            'license_number' => ['sometimes', 'nullable', 'string', 'max:64'],
            'tax_number' => ['sometimes', 'nullable', 'string', 'max:64'],
            'year_established' => ['sometimes', 'nullable', 'integer', 'min:1900', 'max:'.date('Y')],
            'employee_count' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'social_links' => ['sometimes', 'nullable', 'array'],
            'social_links.*' => ['url', 'max:255'],
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
                    $validator->errors()->add('social_links', "Unknown social platform \"{$platform}\".");
                }
            },
        ];
    }
}
