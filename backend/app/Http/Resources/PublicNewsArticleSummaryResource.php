<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicNewsArticleSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'published_at' => $this->published_at->toIso8601String(),
            'image' => [
                'url' => route('api.v1.news.image', ['slug' => $this->slug]),
                'width' => $this->image_width,
                'height' => $this->image_height,
                'alt' => $this->image_alt,
                'credit' => $this->image_credit,
            ],
        ];
    }
}
