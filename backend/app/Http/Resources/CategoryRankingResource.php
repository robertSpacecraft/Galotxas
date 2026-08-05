<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryRankingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'position' => $this['position'],
            'public_display_name' => $this['public_display_name'],
            'played' => $this['played'],
            'wins' => $this['wins'],
            'losses' => $this['losses'],
            'points' => $this['points'],
            'games_for' => $this['games_for'],
            'games_against' => $this['games_against'],
            'games_diff' => $this['games_diff'],
        ];
    }
}
