<?php

namespace App\Services;

use App\Enums\SeasonStatus;
use App\Models\Season;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class SeasonService
{
    public function __construct(private readonly CompetitionImageService $covers) {}

    public const ACTIVE_CONFLICT_ERROR =
        'Ya existe una temporada activa. Finalízala, cancélala o pásala a otro estado antes de activar otra.';

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, ?UploadedFile $image = null): Season
    {
        return $this->persist(new Season, $attributes, $image, false);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Season $season, array $attributes, ?UploadedFile $image = null, bool $removeImage = false): Season
    {
        return $this->persist($season, $attributes, $image, $removeImage);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function persist(Season $season, array $attributes, ?UploadedFile $image, bool $removeImage): Season
    {
        try {
            return $this->covers->mutate($image, $removeImage, function (?string $newKey) use ($season, $attributes, $removeImage): Season {
                Season::query()
                    ->select('id')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $season = $season->exists
                    ? Season::query()->lockForUpdate()->findOrFail($season->getKey())
                    : clone $season;

                if (
                    $attributes['status'] === SeasonStatus::ACTIVE->value
                    && Season::query()
                        ->where('status', SeasonStatus::ACTIVE->value)
                        ->when(
                            $season->exists,
                            fn ($query) => $query->whereKeyNot($season->getKey())
                        )
                        ->exists()
                ) {
                    throw ValidationException::withMessages([
                        'status' => self::ACTIVE_CONFLICT_ERROR,
                    ]);
                }

                $season->fill([
                    'name' => $attributes['name'],
                    'status' => $attributes['status'],
                    'start_date' => $attributes['start_date'] ?? null,
                    'end_date' => $attributes['end_date'] ?? null,
                ]);
                $season->is_public = (bool) $attributes['is_public'];
                $this->covers->saveWithImage($season, $newKey, $removeImage);

                return $season->refresh();
            });
        } catch (QueryException $exception) {
            if ($this->isActiveSlotViolation($exception)) {
                throw ValidationException::withMessages([
                    'status' => self::ACTIVE_CONFLICT_ERROR,
                ]);
            }

            throw $exception;
        }
    }

    private function isActiveSlotViolation(QueryException $exception): bool
    {
        return ($exception->errorInfo[1] ?? null) === 1062
            && str_contains($exception->getMessage(), 'seasons_one_active_unique');
    }
}
