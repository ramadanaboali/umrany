<?php

declare(strict_types=1);

namespace Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\SiteSetting;

/**
 * @property SiteSetting $resource
 */
final class SiteSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'site_name' => $this->resource->site_name,
            'site_title' => $this->resource->site_title,
            'logo_url' => $this->resource->logo_path
                ? Storage::disk('public')->url($this->resource->logo_path)
                : null,
            'contact_email' => $this->resource->contact_email,
            'contact_phone' => $this->resource->contact_phone,
            'contact_address' => $this->resource->contact_address,
            'social_links' => $this->resource->social_links ?? [],
        ];
    }
}
