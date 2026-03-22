<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChampionshipRegistrationRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'championship_id' => $this->championship_id,
            'user_id' => $this->user_id,
            'player_id' => $this->player_id,
            'suggested_category_id' => $this->suggested_category_id,
            'status' => $this->status?->value,
            'payment_status' => $this->payment_status?->value,
            'comment' => $this->comment,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            'championship' => $this->whenLoaded('championship', function () {
                return [
                    'id' => $this->championship?->id,
                    'name' => $this->championship?->name,
                    'slug' => $this->championship?->slug,
                    'type' => $this->championship?->type?->value ?? $this->championship?->type,
                    'registration_status' => $this->championship?->registration_status?->value ?? $this->championship?->registration_status,
                ];
            }),

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
                    'nickname' => $this->player?->nickname,
                    'name' => $this->player?->user?->name,
                    'lastname' => $this->player?->user?->lastname,
                ];
            }),

            'suggested_category' => $this->whenLoaded('suggestedCategory', function () {
                return $this->suggestedCategory
                    ? [
                        'id' => $this->suggestedCategory->id,
                        'name' => $this->suggestedCategory->name,
                        'level' => $this->suggestedCategory->level,
                    ]
                    : null;
            }),
        ];
    }
}
