<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests\Provider;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateCertificateRequest extends FormRequest
{
    private const MAX_FILE_KILOBYTES = 5120;

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
            'name' => ['sometimes', 'string', 'max:255'],
            'issuer' => ['sometimes', 'nullable', 'string', 'max:255'],
            'file' => ['sometimes', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::MAX_FILE_KILOBYTES],
            'issued_date' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
