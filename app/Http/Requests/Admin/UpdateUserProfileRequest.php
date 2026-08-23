<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Models\City;
use Modules\Core\Rules\SaudiOrEgyptianPhoneNumber;
use Modules\Core\Support\PhoneNumber;

/**
 * Mirrors Modules\Core\Http\Requests\Profile\UpdateProfileRequest's rules, but every
 * uniqueness/context check is against the route-bound target user (`$this->route('user')`), never
 * `$this->user()` — the latter resolves to the acting admin under the `admin` guard, not the user
 * whose profile is being edited. See docs/decisions/0027-admin-user-suspend-reactivate.md.
 */
final class UpdateUserProfileRequest extends FormRequest
{
    /** Route-level `can:users.update` middleware already gates this — see routes/admin.php. */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('mobile')) {
            $this->merge(['mobile' => PhoneNumber::normalize($this->string('mobile')->value())]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'full_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)->whereNull('deleted_at')],
            'mobile' => ['sometimes', 'nullable', 'string', 'max:20', new SaudiOrEgyptianPhoneNumber, Rule::unique('users', 'mobile')->ignore($user->id)->whereNull('deleted_at')],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country_id' => ['sometimes', 'nullable', 'exists:countries,id'],
            'city_id' => ['sometimes', 'nullable', 'integer', function (string $attribute, mixed $value, Closure $fail) use ($user): void {
                $city = City::find($value);

                if (! $city) {
                    $fail('The selected city is invalid.');

                    return;
                }

                $countryId = $this->input('country_id') ?? $user->profile?->country_id;

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
                /** @var User $user */
                $user = $this->route('user');

                $resultingEmail = $this->has('email') ? $this->input('email') : $user->email;
                $resultingMobile = $this->has('mobile') ? $this->input('mobile') : $user->mobile;

                if (blank($resultingEmail) && blank($resultingMobile)) {
                    $validator->errors()->add('email', 'The account must keep at least one of email or mobile.');
                }
            },
        ];
    }
}
