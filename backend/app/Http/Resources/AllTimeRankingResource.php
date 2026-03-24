<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllTimeRankingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $player = $this['player'] ?? null;

        return [
            'position' => $this['position'],
            'player_id' => $this['player_id'],
            'name' => $this['name'],

            'played' => $this['played'],
            'wins' => $this['wins'],
            'losses' => $this['losses'],

            'played_singles' => $this['played_singles'],
            'wins_singles' => $this['wins_singles'],
            'losses_singles' => $this['losses_singles'],

            'played_doubles' => $this['played_doubles'],
            'wins_doubles' => $this['wins_doubles'],
            'losses_doubles' => $this['losses_doubles'],

            'raw_points' => round((float) $this['raw_points'], 2),
            'weighted_points' => round((float) $this['weighted_points'], 2),

            'games_for' => round((float) $this['games_for'], 2),
            'games_against' => round((float) $this['games_against'], 2),
            'games_diff' => round((float) $this['games_diff'], 2),

            'win_rate' => round((float) $this['win_rate'], 2),
            'weighted_points_per_match' => round((float) $this['weighted_points_per_match'], 4),
            'games_diff_per_match' => round((float) $this['games_diff_per_match'], 4),

            'official_ranking' => (bool) $this['official_ranking'],
            'matches_needed_for_official_ranking' => $this['matches_needed_for_official_ranking'],

            'championships_played_list' => $this['championships_played_list'] ?? [],
            'categories_played_list' => $this['categories_played_list'] ?? [],

            'player' => $player ? [
                'id' => $player->id,
                'nickname' => $player->nickname,
                'user' => $player->user ? [
                    'id' => $player->user->id,
                    'name' => $player->user->name,
                    'lastname' => $player->user->lastname,
                ] : null,
            ] : null,
        ];
    }
}
