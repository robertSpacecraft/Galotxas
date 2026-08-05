<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChampionshipRankingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'position' => $this['position'],
            'public_display_name' => $this['public_display_name'],
            'played' => $this['played'],
            'wins' => $this['wins'],
            'losses' => $this['losses'],
            'raw_points' => round((float) $this['raw_points'], 2),
            'weighted_points' => round((float) $this['weighted_points'], 2),
            'games_for' => round((float) $this['games_for'], 2),
            'games_against' => round((float) $this['games_against'], 2),
            'games_diff' => round((float) $this['games_diff'], 2),
            'categories_played_count' => $this['categories_played_count'],
            'categories_played_list' => $this['categories_played_list'],
        ];
    }
}
