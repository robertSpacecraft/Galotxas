<?php

namespace App\Services;

use Illuminate\Support\Str;

class ContactRequestFingerprintService
{
    public function ipHash(?string $ip): string
    {
        return $this->keyedHash(trim((string) $ip));
    }

    public function emailHash(?string $email): string
    {
        return $this->keyedHash(
            Str::lower(trim((string) $email))
        );
    }

    public function rateLimitKey(?string $ip, ?string $email): string
    {
        return $this->ipHash($ip).'|'.$this->emailHash($email);
    }

    private function keyedHash(string $value): string
    {
        return hash_hmac(
            'sha256',
            $value,
            (string) config('app.key')
        );
    }
}
