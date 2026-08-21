<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class PublicNewsArticleResource extends PublicNewsArticleSummaryResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'body' => $this->body,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
        ];
    }
}
