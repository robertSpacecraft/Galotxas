<?php

namespace App\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class AdultRequiresDni implements ValidationRule, DataAwareRule
{
    /**
     * Datos completos validados por la request.
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Recibe todos los datos de entrada.
     *
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $birthDate = $this->data['birth_date'] ?? null;

        if (empty($birthDate)) {
            return;
        }

        try {
            $age = Carbon::parse($birthDate)->age;
        } catch (\Throwable $e) {
            return;
        }

        $dni = is_string($value) ? trim($value) : null;

        if ($age >= 18 && empty($dni)) {
            $fail('El DNI es obligatorio para jugadores mayores de edad.');
        }
    }
}
