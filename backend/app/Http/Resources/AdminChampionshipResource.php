<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminChampionshipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'season_id' => $this->season_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'type' => $this->type?->value ?? $this->type,
            'status' => $this->status,
            'is_public' => (bool) $this->is_public,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'registration_status' => $this->registration_status?->value ?? $this->registration_status,
            'registration_starts_at' => $this->registration_starts_at?->toISOString(),
            'registration_ends_at' => $this->registration_ends_at?->toISOString(),
            'season' => $this->whenLoaded('season', fn () => [
                'id' => $this->season?->id,
                'name' => $this->season?->name,
                'is_public' => (bool) $this->season?->is_public,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
