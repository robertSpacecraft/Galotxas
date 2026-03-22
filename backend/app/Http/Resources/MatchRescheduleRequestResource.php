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
            'id' => $this->id,
            'game_match_id' => $this->game_match_id,
            'user_id' => $this->user_id,
            'player_id' => $this->player_id,
            'side' => $this->side?->value,
            'requested_scheduled_date' => $this->requested_scheduled_date?->toISOString(),
            'requested_venue_id' => $this->requested_venue_id,
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

            'requested_venue' => $this->whenLoaded('requestedVenue', function () {
                return [
                    'id' => $this->requestedVenue?->id,
                    'name' => $this->requestedVenue?->name,
                ];
            }),
        ];
    }
}
