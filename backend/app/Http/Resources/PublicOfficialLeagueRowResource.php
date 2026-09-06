<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesOfficialSnapshotPublicName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicOfficialLeagueRowResource extends JsonResource
{
    use ResolvesOfficialSnapshotPublicName;

    /**
     * @return array<string, int|string>
     */
    public function toArray(Request $request): array
    {
        return [
            'position' => $this->position,
            'entry_type' => $this->entry_type,
            'public_display_name' => $this->officialSnapshotPublicName(
                $this->public_display_name,
                $this->public_anonymized_at,
            ),
            'played' => $this->played,
            'wins' => $this->wins,
            'losses' => $this->losses,
            'points' => $this->points,
            'games_for' => $this->games_for,
            'games_against' => $this->games_against,
            'games_diff' => $this->games_diff,
        ];
    }
}
