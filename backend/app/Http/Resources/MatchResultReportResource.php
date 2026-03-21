<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchResultReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'game_match_id' => $this->game_match_id,
            'user_id' => $this->user_id,
            'player_id' => $this->player_id,
            'side' => $this->side?->value,
            'home_score' => $this->home_score,
            'away_score' => $this->away_score,
            'status' => $this->status?->value,
            'comment' => $this->comment,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user?->id,
                    'name' => $this->user?->name,
                    'lastname' => $this->user?->lastname,
                    'email' => $this->user?->email,
                ];
            }),

            'player' => $this->whenLoaded('player', function () {
                return [
                    'id' => $this->player?->id,
                    'name' => $this->player?->user?->name,
                    'lastname' => $this->player?->user?->lastname,
                    'nickname' => $this->player?->nickname,
                ];
            }),
        ];
    }
}
