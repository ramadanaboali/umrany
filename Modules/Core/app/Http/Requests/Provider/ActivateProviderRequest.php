<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests\Provider;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Core\Enums\SocialPlatform;

final class ActivateProviderRequest extends FormRequest
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
            'company_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'country_id' => ['nullable', 'exists:countries,id'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'address' => ['nullable', 'string', 'max:255'],
            'commercial_registration_number' => ['nullable', 'string', 'max:64', 'unique:providers,commercial_registration_number'],
            'license_number' => ['nullable', 'string', 'max:64'],
            'tax_number' => ['nullable', 'string', 'max:64'],
            'year_established' => ['nullable', 'integer', 'min:1900', 'max:'.date('Y')],
            'employee_count' => ['nullable', 'integer', 'min:1'],
            'website' => ['nullable', 'url', 'max:255'],
            'social_links' => ['nullable', 'array'],
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
                // FR-PROVIDER-001 / docs/modules/core.md § Provider: one provider profile per
                // account, enforced here (clear 422) in addition to the DB unique constraint on
                // providers.user_id (which would otherwise surface as an unhandled 500).
                if ($this->user()->provider()->exists()) {
                    $validator->errors()->add('company_name', 'This account already has a provider profile.');
                }

                $this->assertKnownSocialPlatforms($validator);
            },
        ];
    }

    private function assertKnownSocialPlatforms(ValidatorContract $validator): void
    {
        $unknown = array_diff(array_keys($this->input('social_links', [])), SocialPlatform::values());

        foreach ($unknown as $platform) {
            $validator->errors()->add('social_links', "Unknown social platform \"{$platform}\".");
        }
    }
}
