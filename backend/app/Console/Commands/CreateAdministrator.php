<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdministrator extends Command
{
    protected $signature = 'admin:create
        {--email= : Correo del administrador}
        {--name= : Nombre del administrador}
        {--lastname= : Apellidos opcionales}';

    protected $description = 'Crea de forma interactiva e idempotente el administrador inicial';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('Correo'))));
        $name = trim((string) ($this->option('name') ?: $this->ask('Nombre')));
        $lastname = trim((string) ($this->option('lastname') ?: ''));

        $identity = Validator::make(
            compact('email', 'name', 'lastname'),
            [
                'email' => ['required', 'email:rfc', 'max:255'],
                'name' => ['required', 'string', 'max:255'],
                'lastname' => ['nullable', 'string', 'max:255'],
            ]
        );

        if ($identity->fails()) {
            foreach ($identity->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            if ($existing->role === UserRole::ADMIN->value && $existing->active) {
                $this->info('El administrador activo ya existe; no se ha modificado ningún dato.');

                return self::SUCCESS;
            }

            $this->error('El correo ya pertenece a una cuenta que no puede elevarse automáticamente.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Contraseña (mínimo 12 caracteres)');
        $confirmation = (string) $this->secret('Repite la contraseña');

        $credentials = Validator::make(
            compact('password', 'confirmation'),
            [
                'password' => [
                    'required',
                    Password::min(12)->mixedCase()->letters()->numbers()->symbols(),
                ],
                'confirmation' => ['required', 'same:password'],
            ]
        );

        if ($credentials->fails()) {
            foreach ($credentials->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        DB::transaction(static function () use ($email, $name, $lastname, $password): void {
            $user = new User([
                'name' => $name,
                'lastname' => $lastname !== '' ? $lastname : null,
                'email' => $email,
                'password' => $password,
                'role' => UserRole::ADMIN->value,
                'active' => true,
            ]);
            $user->email_verified_at = now();
            $user->save();
        });

        $this->info('Administrador activo creado. La contraseña no se ha mostrado ni registrado.');

        return self::SUCCESS;
    }
}
