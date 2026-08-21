<?php

namespace App\Http\Requests\Admin;

use App\Enums\NewsArticleStatus;
use Illuminate\Validation\Rule;

class StoreNewsArticleRequest extends NewsArticleDataRequest
{
    protected function slugRules(): array
    {
        return ['nullable', ...$this->commonSlugRules()];
    }

    protected function statusRules(): array
    {
        return [
            ...$this->statusEnumRules(),
            Rule::in([NewsArticleStatus::DRAFT->value]),
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Las noticias nuevas deben guardarse primero como borrador.',
        ];
    }
}
