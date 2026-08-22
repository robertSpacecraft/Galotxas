<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicCmsNavigationItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slot' => $this->slot->value,
            'label' => $this->label,
            'url' => "/contenidos/{$this->cmsPage->slug}",
            'sort_order' => $this->sort_order,
        ];
    }
}
