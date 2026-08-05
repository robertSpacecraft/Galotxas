<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryScheduleRoundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'order' => $this->order,
            'status' => $this->status,
            'matches' => $this->whenLoaded('matches', function () {
                return $this->matches->map(function ($match) {
                    return [
                        'id' => $match->id,
                        'scheduled_date' => $match->scheduled_date?->toISOString(),
                        'status' => $match->status?->value ?? $match->status,
                        'home_score' => $match->home_score,
                        'away_score' => $match->away_score,

                        'home_entry' => $match->homeEntry
                            ? new PublicCompetitionEntryResource($match->homeEntry)
                            : null,
                        'away_entry' => $match->awayEntry
                            ? new PublicCompetitionEntryResource($match->awayEntry)
                            : null,

                        'venue' => $match->venue ? [
                            'name' => $match->venue->name,
                        ] : null,
                    ];
                })->values();
            }),
        ];
    }
}
