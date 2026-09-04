<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CategoryOfficialResult;
use Illuminate\Database\Eloquent\Collection;

final readonly class OfficialResultLock
{
    /**
     * @param  Collection<int, CategoryOfficialResult>  $currentOfficialResults
     */
    public function __construct(
        public Category $category,
        public Collection $currentOfficialResults,
    ) {}
}
