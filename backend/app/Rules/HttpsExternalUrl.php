<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class HttpsExternalUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/[\x00-\x20\x7f]/', $value) === 1) {
            $fail($this->message());

            return;
        }

        $parts = parse_url($value);

        if (
            filter_var($value, FILTER_VALIDATE_URL) === false
            || ! is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            $fail($this->message());
        }
    }

    private function message(): string
    {
        return 'La web debe ser una URL externa HTTPS válida y sin credenciales.';
    }
}
