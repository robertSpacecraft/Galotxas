<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryPublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'championship_id' => $this->championship_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'level' => $this->level,
            'gender' => $this->gender?->value ?? $this->gender,
            'status' => $this->status?->value ?? $this->status,

            'championship' => $this->whenLoaded('championship', function () {
                return [
                    'id' => $this->championship?->id,
                    'name' => $this->championship?->name,
                    'slug' => $this->championship?->slug,
                    'type' => $this->championship?->type?->value ?? $this->championship?->type,
                    'status' => $this->championship?->status?->value ?? $this->championship?->status,

                    'season' => $this->championship?->relationLoaded('season') ? [
                        'id' => $this->championship?->season?->id,
                        'name' => $this->championship?->season?->name,
                        'status' => $this->championship?->season?->status?->value ?? $this->championship?->season?->status,
                    ] : null,
                ];
            }),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
