<?php

declare(strict_types=1);

namespace Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\ProviderPortfolio;

/** @mixin ProviderPortfolio */
final class ProviderPortfolioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider_category_id' => $this->provider_category_id,
            'title' => $this->title,
            'description' => $this->description,
            'image_urls' => collect($this->images ?? [])->map(fn (string $path) => Storage::disk('public')->url($path))->all(),
            'videos' => $this->videos ?? [],
            'completion_date' => $this->completion_date?->toDateString(),
            'project_location' => $this->project_location,
            'created_at' => $this->created_at,
        ];
    }
}
