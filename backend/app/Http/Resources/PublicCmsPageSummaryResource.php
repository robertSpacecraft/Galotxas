<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicCmsPageSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'seo_description' => $this->seo_description,
            'published_at' => $this->published_at?->toISOString(),
            'url' => "/contenidos/{$this->slug}",
        ];
    }
}
