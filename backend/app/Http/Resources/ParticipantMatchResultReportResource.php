<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParticipantMatchResultReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'side' => $this->side?->value,
            'home_score' => $this->home_score,
            'away_score' => $this->away_score,
            'status' => $this->status?->value,
            'comment' => $this->comment,
        ];
    }
}
