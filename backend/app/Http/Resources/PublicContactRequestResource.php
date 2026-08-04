<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicContactRequestResource extends JsonResource
{
    /**
     * @return array<string, bool>
     */
    public function toArray(Request $request): array
    {
        return [
            'received' => true,
        ];
    }
}
