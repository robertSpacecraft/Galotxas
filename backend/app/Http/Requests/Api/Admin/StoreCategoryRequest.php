<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Admin\StoreCategoryRequest as BaseStoreCategoryRequest;

class StoreCategoryRequest extends BaseStoreCategoryRequest
{
    public function rules(): array
    {
        return [
            'championship_id' => ['required', 'integer', 'exists:championships,id'],
            ...parent::rules(),
        ];
    }
}
