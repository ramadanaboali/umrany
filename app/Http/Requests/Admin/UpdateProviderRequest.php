<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Models\Provider;

/**
 * Admin-side company-info edit — mirrors Modules\Core\Http\Requests\Provider\
 * UpdateProviderRequest's field set, but resolves uniqueness against the route-bound provider
 * (`$this->route('provider')`), never the acting admin's own account (there is no such thing
 * under the `admin` guard).
 */
final class UpdateProviderRequest extends FormRequest
{
    /** Route-level `can:providers.update` middleware already gates this — see routes/admin.php. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Provider $provider */
        $provider = $this->route('provider');

        return [
            'company_name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'country_id' => ['sometimes', 'nullable', 'exists:countries,id'],
            'city_id' => ['sometimes', 'nullable', 'exists:cities,id'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'commercial_registration_number' => ['sometimes', 'nullable', 'string', 'max:64', Rule::unique('providers', 'commercial_registration_number')->ignore($provider->id)->whereNull('deleted_at')],
            'license_number' => ['sometimes', 'nullable', 'string', 'max:64'],
            'tax_number' => ['sometimes', 'nullable', 'string', 'max:64'],
            'year_established' => ['sometimes', 'nullable', 'integer', 'min:1900', 'max:'.date('Y')],
            'employee_count' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
        ];
    }
}
