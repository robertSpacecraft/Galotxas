<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicContactConfigResource extends JsonResource
{
    /**
     * @return array<string, bool|string>
     */
    public function toArray(Request $request): array
    {
        $enabled = (bool) data_get($this->resource, 'enabled', false);

        if (! $enabled) {
            return ['enabled' => false];
        }

        return [
            'enabled' => true,
            'notice_id' => (string) data_get($this->resource, 'notice_id'),
            'notice_version' => (string) data_get($this->resource, 'notice_version'),
            'privacy_url' => (string) data_get($this->resource, 'privacy_url'),
        ];
    }
}
