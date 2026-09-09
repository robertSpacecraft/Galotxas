<?php

namespace App\Http\Resources;

use App\Services\Media\ResponsiveImageProfile;
use App\Services\Media\ResponsiveMediaResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicNewsArticleSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $image = app(ResponsiveMediaResolver::class)->image(
            $this->image_key,
            ResponsiveImageProfile::NewsCover,
            route('api.v1.news.image', ['slug' => $this->slug]),
            fn (int $width): string => route('api.v1.news.image.variant', [
                'slug' => $this->slug,
                'width' => $width,
            ]),
            $this->image_width,
            $this->image_height,
        );

        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'published_at' => $this->published_at->toIso8601String(),
            'image' => [
                ...$image,
                'alt' => $this->image_alt,
                'credit' => $this->image_credit,
            ],
        ];
    }
}
