<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateCurrencyRequest extends FormRequest
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
            'currency_id' => ['required', 'exists:currencies,id'],
        ];
    }
}
