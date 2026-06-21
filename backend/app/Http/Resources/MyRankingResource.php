<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MyRankingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'championship' => $this->resource['championship'],
            'category' => $this->resource['category'],
            'entry_type' => $this->resource['entry_type'],
            'entry_name' => $this->resource['entry_name'],
            'position' => $this->resource['position'],
            'played' => $this->resource['played'],
            'wins' => $this->resource['wins'],
            'losses' => $this->resource['losses'],
            'points' => $this->resource['points'],
            'games_for' => $this->resource['games_for'],
            'games_against' => $this->resource['games_against'],
            'games_diff' => $this->resource['games_diff'],
        ];
    }
}
