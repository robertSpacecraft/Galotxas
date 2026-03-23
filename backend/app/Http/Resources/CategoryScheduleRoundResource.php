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

                        'home_entry' => $match->homeEntry ? [
                            'id' => $match->homeEntry->id,
                            'entry_type' => $match->homeEntry->entry_type,
                            'player' => $match->homeEntry->player ? [
                                'id' => $match->homeEntry->player->id,
                                'nickname' => $match->homeEntry->player->nickname,
                            ] : null,
                            'team' => $match->homeEntry->team ? [
                                'id' => $match->homeEntry->team->id,
                                'name' => $match->homeEntry->team->name,
                            ] : null,
                        ] : null,

                        'away_entry' => $match->awayEntry ? [
                            'id' => $match->awayEntry->id,
                            'entry_type' => $match->awayEntry->entry_type,
                            'player' => $match->awayEntry->player ? [
                                'id' => $match->awayEntry->player->id,
                                'nickname' => $match->awayEntry->player->nickname,
                            ] : null,
                            'team' => $match->awayEntry->team ? [
                                'id' => $match->awayEntry->team->id,
                                'name' => $match->awayEntry->team->name,
                            ] : null,
                        ] : null,

                        'venue' => $match->venue ? [
                            'id' => $match->venue->id,
                            'name' => $match->venue->name,
                        ] : null,
                    ];
                })->values();
            }),
        ];
    }
}
