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
            'round_id' => $this->round_id,
            'venue_id' => $this->venue_id,
            'home_entry_id' => $this->home_entry_id,
            'away_entry_id' => $this->away_entry_id,
            'winner_entry_id' => $isValidated ? $this->winner_entry_id : null,

            'scheduled_date' => $this->scheduled_date?->toISOString(),
            'status' => $this->status?->value,
            'home_score' => $isValidated ? $this->home_score : null,
            'away_score' => $isValidated ? $this->away_score : null,


            'home_entry' => $this->whenLoaded('homeEntry', function () {
                return $this->transformEntry($this->homeEntry);
            }),

            'away_entry' => $this->whenLoaded('awayEntry', function () {
                return $this->transformEntry($this->awayEntry);
            }),

            'winner_entry' => $this->whenLoaded('winnerEntry', function () use ($isValidated) {
                return $isValidated && $this->winnerEntry ? $this->transformEntry($this->winnerEntry) : null;
            }),

            'venue' => $this->whenLoaded('venue', function () {
                return [
                    'id' => $this->venue?->id,
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

    /**
     * @return array<string, mixed>|null
     */
    protected function transformEntry($entry): ?array
    {
        if (!$entry) {
            return null;
        }

        return [
            'id' => $entry->id,
            'entry_type' => $entry->entry_type,
            'player_id' => $entry->player_id,
            'team_id' => $entry->team_id,

            'player' => $entry->relationLoaded('player') && $entry->player
                ? [
                    'id' => $entry->player->id,
                    'name' => $entry->player->user?->name,
                    'lastname' => $entry->player->user?->lastname,
                    'nickname' => $entry->player->nickname,
                ]
                : null,

            'team' => $entry->relationLoaded('team') && $entry->team
                ? [
                    'id' => $entry->team->id,
                    'name' => $entry->team->name,
                    'players' => $entry->team->relationLoaded('players')
                        ? $entry->team->players->map(function ($player) {
                            return [
                                'id' => $player->id,
                                'name' => $player->user?->name,
                                'lastname' => $player->user?->lastname,
                                'nickname' => $player->nickname,
                                'role_in_team' => $player->pivot?->role_in_team,
                            ];
                        })->values()
                        : [],
                ]
                : null,
        ];
    }
}
