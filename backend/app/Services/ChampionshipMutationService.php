<?php

namespace App\Services;

use App\Enums\ChampionshipType;
use App\Enums\OfficialResultMutationImpact;
use App\Models\Category;
use App\Models\CategoryRegistration;
use App\Models\Championship;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
        return $this->covers->mutate($image, $removeImage, function (?string $newKey, callable $obsolete) use ($championship, $validated, $removeImage): Championship {
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
                $participants = $this->locks->lockEntriesAndTeams($categoryIds);

                $this->assertTypeChangeIsSafe($categoryIds, $participants);
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
            $this->covers->saveWithImage($championship, $newKey, $removeImage, $obsolete);

            return $championship->refresh();
        });
    }

    /**
     * A singles/doubles change would leave registrations, entries and teams that
     * contradict the new modality, so it is rejected instead of repaired. Entries
     * and teams come from the rows already locked in the canonical order; the
     * registrations are read with a locking read so the check sees committed data.
     *
     * @param  Collection<int, int>  $categoryIds
     * @param  array{entries: Collection<int, mixed>, teams: Collection<int, mixed>}  $participants
     */
    private function assertTypeChangeIsSafe(Collection $categoryIds, array $participants): void
    {
        $registrations = $categoryIds->isEmpty()
            ? 0
            : CategoryRegistration::query()
                ->whereIn('category_id', $categoryIds->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->count();

        $dependencies = array_filter([
            'inscripciones' => $registrations,
            'participantes' => $participants['entries']->count(),
            'equipos' => $participants['teams']->count(),
        ]);

        if ($dependencies === []) {
            return;
        }

        throw ValidationException::withMessages([
            'type' => sprintf(
                'No se puede cambiar el tipo del campeonato mientras sus categorías tengan %s. '
                .'Retira primero esa configuración de competición.',
                collect($dependencies)
                    ->map(fn (int $count, string $label): string => sprintf('%s (%d)', $label, $count))
                    ->implode(', ')
            ),
        ]);
    }
}
