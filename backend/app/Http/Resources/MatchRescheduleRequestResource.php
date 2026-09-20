<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchRescheduleRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'side' => $this->side?->value,
            'requested_scheduled_date' => $this->requested_scheduled_date?->toISOString(),
            'status' => $this->status?->value,
            'comment' => $this->comment,

            'requested_venue' => $this->whenLoaded('requestedVenue', function () {
                return [
                    'id' => $this->requestedVenue?->id,
                    'name' => $this->requestedVenue?->name,
                ];
            }),
        ];
    }
}
