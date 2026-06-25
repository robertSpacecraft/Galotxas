<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicCmsPageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'published_at' => $this->published_at?->toISOString(),
            'blocks' => PublicCmsBlockResource::collection($this->whenLoaded('blocks')),
        ];
    }
}
