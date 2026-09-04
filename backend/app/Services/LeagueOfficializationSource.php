<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CategoryEntry;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final readonly class LeagueOfficializationSource
{
    /**
     * @param  EloquentCollection<int, CategoryEntry>  $entryModels
     * @param  list<array<string, mixed>>  $entries
     * @param  list<array<string, int|string|null>>  $matches
     * @param  list<array<string, int>>  $ranking
     */
    public function __construct(
        public Category $category,
        public string $championshipType,
        public int $targetScore,
        public EloquentCollection $entryModels,
        public array $entries,
        public array $matches,
        public array $ranking,
    ) {}

    /** @return list<int> */
    public function playerEntrySourceIds(): array
    {
        return collect($this->entries)
            ->pluck('source_player_id')
            ->filter(fn ($id): bool => $id !== null)
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();
    }
}
