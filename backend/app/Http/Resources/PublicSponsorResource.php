<?php

namespace App\Http\Resources;

use App\Services\Media\ResponsiveImageProfile;
use App\Services\Media\ResponsiveMediaResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicSponsorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $logo = app(ResponsiveMediaResolver::class)->image(
            $this->logo_key,
            ResponsiveImageProfile::SponsorLogo,
            route('api.v1.sponsors.logo', $this->resource),
            fn (int $width): string => route('api.v1.sponsors.logo.variant', [
                'sponsor' => $this->resource,
                'width' => $width,
            ]),
            $this->logo_width,
            $this->logo_height,
        );

        return [
            'id' => $this->id,
            'name' => $this->name,
            'logo' => $logo,
            'website_url' => $this->website_url,
        ];
    }
}
