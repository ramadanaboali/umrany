<?php

declare(strict_types=1);

namespace Modules\Core\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin User */
final class ProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $profile = $this->profile;

        return [
            'id' => $this->id,
            'full_name' => $profile?->full_name,
            'avatar_url' => $profile?->avatar_path !== null ? Storage::disk('public')->url($profile->avatar_path) : null,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'email_verified' => $this->email_verified_at !== null,
            'mobile_verified' => $this->mobile_verified_at !== null,
            'country' => $profile?->country !== null ? [
                'id' => $profile->country->id,
                'name_en' => $profile->country->name_en,
                'name_ar' => $profile->country->name_ar,
            ] : null,
            'city' => $profile?->city !== null ? [
                'id' => $profile->city->id,
                'name_en' => $profile->city->name_en,
                'name_ar' => $profile->city->name_ar,
            ] : null,
            'address' => $profile?->address,
            'preferred_language' => $profile?->preferred_language?->value,
            'preferred_currency' => $profile?->currency !== null ? [
                'id' => $profile->currency->id,
                'code' => $profile->currency->code,
                'symbol' => $profile->currency->symbol,
            ] : null,
        ];
    }
}
