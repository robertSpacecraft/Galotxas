<?php

namespace App\Services;

use App\Enums\SeasonStatus;
use App\Models\Season;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SeasonService
{
    public const ACTIVE_CONFLICT_ERROR =
        'Ya existe una temporada activa. Finalízala, cancélala o pásala a otro estado antes de activar otra.';

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Season
    {
        return $this->persist(new Season, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Season $season, array $attributes): Season
    {
        return $this->persist($season, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function persist(Season $season, array $attributes): Season
    {
        try {
            return DB::transaction(function () use ($season, $attributes): Season {
                Season::query()
                    ->select('id')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

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
                $season->save();

                return $season->refresh();
            }, 3);
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
