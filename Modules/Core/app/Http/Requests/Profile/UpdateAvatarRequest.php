<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateAvatarRequest extends FormRequest
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
        // FR-PROFILE-003: "maximum upload size is configurable" — config('core.avatar.max_kilobytes').
        return [
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('core.avatar.max_kilobytes')],
        ];
    }
}
