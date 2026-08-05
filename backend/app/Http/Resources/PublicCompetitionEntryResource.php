<?php

namespace App\Http\Resources;

use App\Services\PublicPlayerIdentityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicCompetitionEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'entry_type' => $this->entry_type,
            'public_display_name' => $this->displayName(),
        ];
    }

    private function displayName(): string
    {
        return app(PublicPlayerIdentityService::class)->entryDisplayName($this->resource);
    }
}
