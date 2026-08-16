<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicSponsorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'logo' => [
                'url' => route('api.v1.sponsors.logo', $this->resource),
                'width' => $this->logo_width,
                'height' => $this->logo_height,
            ],
            'website_url' => $this->website_url,
        ];
    }
}
