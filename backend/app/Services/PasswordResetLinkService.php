<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Throwable;

class PasswordResetLinkService
{
    public function send(string $email): void
    {
        try {
            Password::sendResetLink(['email' => $email]);
        } catch (Throwable $exception) {
            $this->deleteIssuedToken($email);

            $failureCode = preg_replace(
                '/[^A-Za-z0-9_\\-]/',
                '',
                class_basename($exception)
            ) ?: 'DeliveryError';

            Log::error('No se pudo entregar un enlace de recuperación de contraseña.', [
                'failure_code' => $failureCode,
            ]);
        }
    }

    private function deleteIssuedToken(string $email): void
    {
        try {
            $broker = Password::broker();
            $user = $broker->getUser(['email' => $email]);

            if ($user !== null) {
                $broker->deleteToken($user);
            }
        } catch (Throwable) {
            // The original delivery failure remains observable without exposing sensitive data.
        }
    }
}
