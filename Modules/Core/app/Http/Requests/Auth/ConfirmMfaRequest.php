<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class ConfirmMfaRequest extends FormRequest
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
            // TOTP only — confirming a pending enrollment, so no recovery codes exist yet.
            'code' => ['required', 'digits:6'],
        ];
    }
}
