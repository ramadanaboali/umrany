<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests\Profile;

use Closure;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Models\City;
use Modules\Core\Rules\SaudiOrEgyptianPhoneNumber;

final class UpdateProfileRequest extends FormRequest
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
            'full_name' => ['sometimes', 'string', 'max:255'],
            // FR-PROFILE-002: email/mobile changes must stay unique platform-wide and re-trigger
            // verification — handled in Modules\Core\Actions\Profile\UpdateProfile, not here.
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'mobile' => ['sometimes', 'nullable', 'string', 'max:20', new SaudiOrEgyptianPhoneNumber, Rule::unique('users', 'mobile')->ignore($this->user()->id)],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country_id' => ['sometimes', 'nullable', 'exists:countries,id'],
            'city_id' => ['sometimes', 'nullable', 'integer', function (string $attribute, mixed $value, Closure $fail): void {
                $city = City::find($value);

                if (! $city) {
                    $fail('The selected city is invalid.');

                    return;
                }

                $countryId = $this->input('country_id') ?? $this->user()->profile?->country_id;

                if ($countryId !== null && (int) $city->country_id !== (int) $countryId) {
                    $fail('The selected city does not belong to the selected country.');
                }
            }],
        ];
    }

    /**
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function (ValidatorContract $validator): void {
                $user = $this->user();
                $resultingEmail = $this->has('email') ? $this->input('email') : $user->email;
                $resultingMobile = $this->has('mobile') ? $this->input('mobile') : $user->mobile;

                if (blank($resultingEmail) && blank($resultingMobile)) {
                    $validator->errors()->add('email', 'The account must keep at least one of email or mobile.');
                }
            },
        ];
    }
}
