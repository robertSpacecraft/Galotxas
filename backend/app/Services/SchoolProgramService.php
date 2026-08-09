<?php

namespace App\Services;

use App\Models\SchoolProgram;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SchoolProgramService
{
    public const PUBLICATION_ERROR =
        'Sólo puede existir un programa público de Escuela de Galotxas.';

    public const OPENING_ERROR =
        'No se pueden abrir las inscripciones hasta completar la configuración operativa.';

    public function __construct(
        private readonly SchoolEnrollmentAvailabilityService $availability
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): SchoolProgram
    {
        return $this->persist(new SchoolProgram, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(SchoolProgram $program, array $attributes): SchoolProgram
    {
        return $this->persist($program, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function persist(SchoolProgram $program, array $attributes): SchoolProgram
    {
        try {
            return DB::transaction(function () use ($program, $attributes): SchoolProgram {
                SchoolProgram::query()
                    ->effectivelyPublic()
                    ->lockForUpdate()
                    ->get();

                if (
                    (bool) $attributes['is_public']
                    && SchoolProgram::query()
                        ->effectivelyPublic()
                        ->whereKeyNot($program->getKey())
                        ->exists()
                ) {
                    throw ValidationException::withMessages([
                        'is_public' => self::PUBLICATION_ERROR,
                    ]);
                }

                $program->fill($attributes);

                if ($program->enrollments_open) {
                    $issues = $this->availability->readinessIssues($program);
                    if ($issues !== []) {
                        throw ValidationException::withMessages([
                            'enrollments_open' => [self::OPENING_ERROR, ...$issues],
                        ]);
                    }
                }

                $program->save();

                return $program->refresh();
            });
        } catch (QueryException $exception) {
            if ($this->isExclusivePublicationViolation($exception)) {
                throw ValidationException::withMessages([
                    'is_public' => self::PUBLICATION_ERROR,
                ]);
            }

            throw $exception;
        }
    }

    private function isExclusivePublicationViolation(QueryException $exception): bool
    {
        return ($exception->errorInfo[1] ?? null) === 1062
            && str_contains($exception->getMessage(), 'school_programs_one_public_unique');
    }
}
