<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicSchoolScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'day_of_week' => $this->day_of_week?->value ?? $this->day_of_week,
            'starts_at' => $this->startsAtLabel(),
            'ends_at' => $this->endsAtLabel(),
            'location' => $this->relationLoaded('location')
                ? new PublicSchoolLocationResource($this->location)
                : null,
        ];
    }
}
