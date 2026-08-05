<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicMatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isValidated = ($this->status?->value ?? $this->status) === 'validated';

        return [
            'id' => $this->id,
            'scheduled_date' => $this->scheduled_date?->toISOString(),
            'status' => $this->status?->value,
            'home_score' => $isValidated ? $this->home_score : null,
            'away_score' => $isValidated ? $this->away_score : null,
            'home_entry' => $this->whenLoaded('homeEntry', function () {
                return $this->homeEntry ? new PublicCompetitionEntryResource($this->homeEntry) : null;
            }),
            'away_entry' => $this->whenLoaded('awayEntry', function () {
                return $this->awayEntry ? new PublicCompetitionEntryResource($this->awayEntry) : null;
            }),
            'winner_entry' => $this->whenLoaded('winnerEntry', function () use ($isValidated) {
                return $isValidated && $this->winnerEntry
                    ? new PublicCompetitionEntryResource($this->winnerEntry)
                    : null;
            }),
            'venue' => $this->whenLoaded('venue', function () {
                return [
                    'name' => $this->venue?->name,
                ];
            }),

            'round' => $this->whenLoaded('round', function () {
                return [
                    'id' => $this->round?->id,
                    'name' => $this->round?->name,
                    'stage' => $this->round?->stage,
                    'order' => $this->round?->order,
                    'category' => $this->round?->relationLoaded('category') && $this->round?->category
                        ? [
                            'id' => $this->round->category->id,
                            'name' => $this->round->category->name,
                            'slug' => $this->round->category->slug,
                            'level' => $this->round->category->level,
                            'gender' => $this->round->category->gender?->value ?? $this->round->category->gender,
                            'status' => $this->round->category->status?->value ?? $this->round->category->status,
                            'championship' => $this->round->category->relationLoaded('championship') && $this->round->category->championship
                                ? [
                                    'id' => $this->round->category->championship->id,
                                    'name' => $this->round->category->championship->name,
                                    'slug' => $this->round->category->championship->slug,
                                    'type' => $this->round->category->championship->type?->value ?? $this->round->category->championship->type,
                                    'season' => $this->round->category->championship->relationLoaded('season') && $this->round->category->championship->season
                                        ? [
                                            'id' => $this->round->category->championship->season->id,
                                            'name' => $this->round->category->championship->season->name,
                                            'status' => $this->round->category->championship->season->status?->value ?? $this->round->category->championship->season->status,
                                        ]
                                        : null,
                                ]
                                : null,
                        ]
                        : null,
                ];
            }),
        ];
    }
}
