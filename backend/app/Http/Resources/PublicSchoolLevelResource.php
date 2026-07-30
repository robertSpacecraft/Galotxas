<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicSchoolLevelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'minimum_age' => $this->minimum_age,
            'maximum_age' => $this->maximum_age,
            'schedules' => $this->relationLoaded('schedules')
                ? PublicSchoolScheduleResource::collection($this->schedules)
                : [],
        ];
    }
}
