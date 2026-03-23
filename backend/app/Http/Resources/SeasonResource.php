<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeasonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status?->value ?? $this->status,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),

            'championships' => $this->whenLoaded('championships', function () {
                return $this->championships->map(function ($championship) {
                    return [
                        'id' => $championship->id,
                        'name' => $championship->name,
                        'slug' => $championship->slug,
                        'type' => $championship->type?->value ?? $championship->type,
                        'status' => $championship->status?->value ?? $championship->status,
                        'categories_count' => $championship->categories?->count() ?? 0,
                    ];
                })->values();
            }),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
