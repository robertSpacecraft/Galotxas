<?php

namespace App\Services;

use App\Enums\ChampionshipType;
use App\Enums\OfficialResultMutationImpact;
use App\Models\Category;
use App\Models\Championship;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ChampionshipMutationService
{
    public function __construct(
        private readonly CompetitionImageService $covers,
        private readonly OfficialResultMutationGuard $guard,
        private readonly OfficialResultLockService $locks,
    ) {}

    public function create(array $validated, ?UploadedFile $image = null): Championship
    {
        return $this->persist(new Championship, $validated, $image, false);
    }

    public function update(Championship $championship, array $validated, ?UploadedFile $image = null, bool $removeImage = false): Championship
    {
        return $this->persist($championship, $validated, $image, $removeImage);
    }

    private function persist(Championship $championship, array $validated, ?UploadedFile $image, bool $removeImage): Championship
    {
        return $this->covers->mutate($image, $removeImage, function (?string $newKey) use ($championship, $validated, $removeImage): Championship {
            $championship = $championship->exists
                ? Championship::query()->lockForUpdate()->findOrFail($championship->getKey())
                : clone $championship;
            $currentType = $championship->type instanceof ChampionshipType
                ? $championship->type->value
                : (string) $championship->type;

            if ($championship->exists && $currentType !== $validated['type']) {
                $categoryIds = Category::query()
                    ->where('championship_id', $championship->id)
                    ->orderBy('id')
                    ->pluck('id');

                $this->guard->lockAndGuardCategories(
                    $categoryIds,
                    OfficialResultMutationImpact::COMPETITION_RULES
                );
                $this->locks->lockRoundsAndMatches($categoryIds);
                $this->locks->lockEntriesAndTeams($categoryIds);
            }

            $championship->fill([
                'season_id' => $validated['season_id'],
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']),
                'description' => $validated['description'] ?? null,
                'type' => $validated['type'],
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
                'status' => $validated['status'],
                'registration_status' => $validated['registration_status'],
                'registration_starts_at' => $validated['registration_starts_at'] ?? null,
                'registration_ends_at' => $validated['registration_ends_at'] ?? null,
            ]);
            $championship->is_public = (bool) $validated['is_public'];
            $this->covers->saveWithImage($championship, $newKey, $removeImage);

            return $championship->refresh();
        });
    }
}
