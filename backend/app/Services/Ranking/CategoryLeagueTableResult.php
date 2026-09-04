<?php

namespace App\Services\Ranking;

use Illuminate\Support\Collection;

final readonly class CategoryLeagueTableResult
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  list<list<int>>  $unsupportedTieGroups
     */
    public function __construct(
        public Collection $rows,
        public array $unsupportedTieGroups,
    ) {}

    public function isSportinglyResolved(): bool
    {
        return $this->unsupportedTieGroups === [];
    }
}
