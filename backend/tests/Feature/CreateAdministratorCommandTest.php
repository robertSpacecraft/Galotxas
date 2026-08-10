<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdministratorCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_an_active_verified_admin_with_a_hidden_strong_password(): void
    {
        $password = 'Strong-Admin!284';

        $this->artisan('admin:create', [
            '--email' => 'ADMIN@example.test',
            '--name' => 'Administración',
            '--lastname' => 'Inicial',
        ])
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', $password)
            ->expectsQuestion('Repite la contraseña', $password)
            ->expectsOutputToContain('Administrador activo creado')
            ->assertSuccessful();

        $admin = User::query()->where('email', 'admin@example.test')->sole();

        $this->assertSame(UserRole::ADMIN->value, $admin->role);
        $this->assertTrue($admin->active);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check($password, $admin->password));
        $this->assertNotSame($password, $admin->password);
    }

    public function test_command_rejects_weak_or_mismatched_passwords_without_creating_a_user(): void
    {
        $this->artisan('admin:create', [
            '--email' => 'admin@example.test',
            '--name' => 'Administración',
        ])
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'weak')
            ->expectsQuestion('Repite la contraseña', 'different')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_command_is_idempotent_for_an_existing_active_admin(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@example.test',
            'active' => true,
        ]);

        $this->artisan('admin:create', [
            '--email' => 'admin@example.test',
            '--name' => 'Otro nombre',
        ])
            ->expectsOutputToContain('ya existe')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_command_never_elevates_a_normal_or_inactive_existing_account(): void
    {
        User::factory()->create(['email' => 'user@example.test']);
        User::factory()->admin()->create([
            'email' => 'inactive@example.test',
            'active' => false,
        ]);

        foreach (['user@example.test', 'inactive@example.test'] as $email) {
            $this->artisan('admin:create', [
                '--email' => $email,
                '--name' => 'Administración',
            ])
                ->expectsOutputToContain('no puede elevarse automáticamente')
                ->assertFailed();
        }

        $this->assertSame(
            UserRole::USER->value,
            User::query()->where('email', 'user@example.test')->value('role')
        );
        $this->assertFalse(
            (bool) User::query()->where('email', 'inactive@example.test')->value('active')
        );
    }
}
