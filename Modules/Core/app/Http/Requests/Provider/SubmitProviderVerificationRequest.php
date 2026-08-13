<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests\Provider;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Enums\ProviderDocumentType;

final class SubmitProviderVerificationRequest extends FormRequest
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
        return [
            'documents' => ['required', 'array', 'min:1'],
            'documents.*.type' => ['required', Rule::enum(ProviderDocumentType::class)],
            'documents.*.file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (ValidatorContract $validator): void {
                $verification = $this->user()->provider?->verification;

                if ($verification !== null && ! $verification->status->canSubmit()) {
                    $validator->errors()->add('documents', "Verification cannot be resubmitted while its status is \"{$verification->status->value}\".");
                }
            },
        ];
    }
}
