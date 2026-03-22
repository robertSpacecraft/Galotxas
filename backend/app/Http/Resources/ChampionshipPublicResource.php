<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChampionshipPublicResource extends JsonResource
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
            'status' => $this->status?->value ?? $this->status,

            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),

            'registration_status' => $this->registration_status?->value ?? $this->registration_status,
            'registration_starts_at' => $this->registration_starts_at?->toISOString(),
            'registration_ends_at' => $this->registration_ends_at?->toISOString(),
            'registration_is_open' => method_exists($this->resource, 'registrationIsOpen')
                ? $this->resource->registrationIsOpen()
                : false,

            'season' => $this->whenLoaded('season', function () {
                return [
                    'id' => $this->season?->id,
                    'name' => $this->season?->name,
                    'status' => $this->season?->status?->value ?? $this->season?->status,
                ];
            }),

            'categories' => $this->whenLoaded('categories', function () {
                return $this->categories->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'slug' => $category->slug,
                        'level' => $category->level,
                        'gender' => $category->gender?->value ?? $category->gender,
                        'status' => $category->status?->value ?? $category->status,
                    ];
                })->values();
            }),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
