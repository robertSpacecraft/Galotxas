<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesOfficialSnapshotPublicName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicOfficialCupWinnerResource extends JsonResource
{
    use ResolvesOfficialSnapshotPublicName;

    /**
     * @return array<string, string>
     */
    public function toArray(Request $request): array
    {
        return [
            'entry_type' => $this->entry_type,
            'public_display_name' => $this->officialSnapshotPublicName(
                $this->public_display_name,
                $this->public_anonymized_at,
            ),
        ];
    }
}
