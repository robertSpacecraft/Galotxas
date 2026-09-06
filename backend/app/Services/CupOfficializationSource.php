<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CategoryEntry;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final readonly class CupOfficializationSource
{
    /**
     * @param  EloquentCollection<int, CategoryEntry>  $seedEntryModels
     * @param  list<array<string, mixed>>  $seed
     * @param  list<array<string, int|string>>  $matches
     * @param  array{source_entry_id: int, source_final_match_id: int}  $champion
     */
    public function __construct(
        public Category $category,
        public string $championshipType,
        public int $targetScore,
        public EloquentCollection $seedEntryModels,
        public array $seed,
        public array $matches,
        public array $champion,
    ) {}

    public function championEntryModel(): ?CategoryEntry
    {
        return $this->seedEntryModels->firstWhere(
            'id',
            $this->champion['source_entry_id'],
        );
    }

    /** @return list<int> */
    public function championPlayerSourceIds(): array
    {
        $champion = collect($this->seed)->firstWhere(
            'source_entry_id',
            $this->champion['source_entry_id'],
        );

        return isset($champion['source_player_id'])
            ? [(int) $champion['source_player_id']]
            : [];
    }
}
