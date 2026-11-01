<?php

declare(strict_types=1);

namespace Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\ProviderCertificate;

/** @mixin ProviderCertificate */
final class ProviderCertificateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'issuer' => $this->issuer,
            'file_url' => $this->file_path !== null ? Storage::disk('public')->url($this->file_path) : null,
            'issued_date' => $this->issued_date?->toDateString(),
            'created_at' => $this->created_at,
        ];
    }
}
