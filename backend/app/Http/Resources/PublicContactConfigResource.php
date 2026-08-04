<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicContactConfigResource extends JsonResource
{
    /**
     * @return array<string, bool>
     */
    public function toArray(Request $request): array
    {
        return [
            'enabled' => (bool) data_get($this->resource, 'enabled', false),
        ];
    }
}
