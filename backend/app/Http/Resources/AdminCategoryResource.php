<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'championship_id' => $this->championship_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'level' => $this->level,
            'gender' => $this->gender?->value ?? $this->gender,
            'status' => $this->status,
            'is_public' => (bool) $this->is_public,
            'championship' => $this->whenLoaded('championship', fn () => [
                'id' => $this->championship?->id,
                'season_id' => $this->championship?->season_id,
                'name' => $this->championship?->name,
                'slug' => $this->championship?->slug,
                'is_public' => (bool) $this->championship?->is_public,
                'season' => $this->championship?->relationLoaded('season') ? [
                    'id' => $this->championship?->season?->id,
                    'name' => $this->championship?->season?->name,
                    'is_public' => (bool) $this->championship?->season?->is_public,
                ] : null,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
