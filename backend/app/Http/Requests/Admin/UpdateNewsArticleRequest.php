<?php

namespace App\Http\Requests\Admin;

use App\Models\NewsArticle;

class UpdateNewsArticleRequest extends NewsArticleDataRequest
{
    protected function slugRules(): array
    {
        $article = $this->route('news_article');

        return [
            'required',
            ...$this->commonSlugRules($article instanceof NewsArticle ? $article : null),
        ];
    }

    protected function statusRules(): array
    {
        return $this->statusEnumRules();
    }
}
