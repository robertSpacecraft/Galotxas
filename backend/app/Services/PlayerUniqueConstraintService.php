<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class PlayerUniqueConstraintService
{
    public function rethrowAsValidation(QueryException $exception): never
    {
        if (($exception->errorInfo[1] ?? null) !== 1062) {
            throw $exception;
        }

        if (str_contains($exception->getMessage(), 'players_nickname_unique')) {
            throw ValidationException::withMessages([
                'nickname' => 'Este apodo deportivo ya está en uso.',
            ]);
        }

        if (str_contains($exception->getMessage(), 'players_license_number_unique')) {
            throw ValidationException::withMessages([
                'license_number' => 'Este número de licencia ya está en uso.',
            ]);
        }

        throw $exception;
    }
}
