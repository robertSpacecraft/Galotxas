<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicCategoryOfficialResultsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $league = $this->resource['league'];
        $cup = $this->resource['cup'];

        return [
            'league' => $league === null
                ? null
                : new PublicOfficialLeagueResultResource($league),
            'cup' => $cup === null
                ? null
                : new PublicOfficialCupResultResource($cup),
        ];
    }
}
