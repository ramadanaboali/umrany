<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests\Provider;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FR-PROVIDER-004: no request body — the check runs against the provider's own on-file
 * commercial_registration_number, not a freshly submitted one.
 */
final class RequestGovernmentVerificationRequest extends FormRequest
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
        return [];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (ValidatorContract $validator): void {
                if ($this->user()->provider?->commercial_registration_number === null) {
                    $validator->errors()->add('commercial_registration_number', 'A commercial registration number is required before requesting government verification.');
                }
            },
        ];
    }
}
