<?php

declare(strict_types=1);

namespace Modules\Core\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'email_verified' => $this->email_verified_at !== null,
            'mobile_verified' => $this->mobile_verified_at !== null,
            'status' => $this->status->value,
            'created_at' => $this->created_at,
        ];
    }
}
