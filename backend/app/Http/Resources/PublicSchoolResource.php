<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicSchoolResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'enrollments_open' => $this->acceptsPublicEnrollments(),
            'contact' => [
                'phone' => $this->contact_phone,
                'email' => $this->contact_email,
            ],
            'default_location' => $this->relationLoaded('defaultLocation')
                && $this->defaultLocation !== null
                    ? new PublicSchoolLocationResource($this->defaultLocation)
                    : null,
            'levels' => $this->relationLoaded('levels')
                ? PublicSchoolLevelResource::collection($this->levels)
                : [],
        ];
    }
}
