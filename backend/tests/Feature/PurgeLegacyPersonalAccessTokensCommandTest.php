<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithUserCredentials;
use Tests\TestCase;

class PurgeLegacyPersonalAccessTokensCommandTest extends TestCase
{
    use InteractsWithUserCredentials;
    use RefreshDatabase;

    private const CONFIRM = 'PURGE-LEGACY-PATS';

    protected function setUp(): void
    {
        parent::setUp();

        $this->retired();
    }

    private function retired(): void
    {
        config()->set('legacy_bearer.issuance_enabled', false);
        config()->set('legacy_bearer.acceptance_enabled', false);
    }

    private function insertTokens(string $type, int $id, int $count, string $prefix): void
    {
        $rows = [];

        for ($index = 0; $index < $count; $index++) {
            $rows[] = [
                'tokenable_type' => $type,
                'tokenable_id' => $id,
                'name' => 'api-token',
                'token' => hash('sha256', $prefix.$index),
                'abilities' => '["*"]',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('personal_access_tokens')->insert($rows);
    }

    private function purge(array $options = []): array
    {
        $code = Artisan::call('auth:purge-legacy-pats', $options);

        return [$code, Artisan::output()];
    }

    private function userTokens(): int
    {
        return DB::table('personal_access_tokens')->where('tokenable_type', User::class)->count();
    }

    public function test_dry_run_is_the_default_reports_the_count_and_writes_nothing(): void
    {
        $user = User::factory()->create();
        $this->insertTokens(User::class, $user->id, 3, 'a');
        $before = DB::table('personal_access_tokens')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower(ltrim($query->sql));
        });

        [$code, $output] = $this->purge();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('SIMULACRO (DRY RUN)', $output);
        $this->assertStringNotContainsString('ELIMINACIÓN COMPLETADA', $output);
        $this->assertStringContainsString('Tokens de User que se eliminarían: 3', $output);
        $this->assertSame(
            [],
            array_values(array_filter($queries, fn (string $sql): bool => ! str_starts_with($sql, 'select'))),
        );
        $this->assertSame($before, DB::table('personal_access_tokens')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all());
    }

    public function test_confirmed_purge_deletes_every_user_token_including_orphans_and_nothing_else(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $this->insertTokens(User::class, $user->id, 3, 'u');
        $this->insertTokens(User::class, $admin->id, 2, 'a');
        $this->insertTokens(User::class, 987654, 2, 'orphan');
        $this->insertTokens('App\\Models\\Other', 5, 4, 'other');
        $sessionId = $this->createDurableSession($user);

        $users = DB::table('users')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        $sessions = DB::table('sessions')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();

        [$code, $output] = $this->purge(['--confirm' => self::CONFIRM]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('ELIMINACIÓN COMPLETADA', $output);
        $this->assertStringContainsString('Tokens de User eliminados: 7', $output);
        $this->assertStringContainsString('Tokens de User restantes: 0', $output);
        $this->assertStringContainsString('(intactos): 4', $output);
        $this->assertSame(0, $this->userTokens());
        $this->assertSame(4, DB::table('personal_access_tokens')->where('tokenable_type', 'App\\Models\\Other')->count());
        $this->assertSame($users, DB::table('users')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all());
        $this->assertSame($sessions, DB::table('sessions')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all());
        $this->assertDatabaseHas('sessions', ['id' => $sessionId]);
        $this->assertTrue(Schema::hasTable('personal_access_tokens'));
    }

    public function test_deletion_is_chunked_across_several_batches(): void
    {
        $user = User::factory()->create();
        $this->insertTokens(User::class, $user->id, 25, 'chunk');
        $deletes = 0;
        DB::listen(function ($query) use (&$deletes): void {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'delete')) {
                $deletes++;
            }
        });

        [$code, $output] = $this->purge(['--confirm' => self::CONFIRM, '--chunk' => 10]);

        $this->assertSame(0, $code);
        $this->assertSame(3, $deletes);
        $this->assertStringContainsString('Tokens de User eliminados: 25 en 3 lotes', $output);
        $this->assertSame(0, $this->userTokens());
    }

    public function test_the_delete_statement_itself_is_scoped_to_user_tokens(): void
    {
        $user = User::factory()->create();
        $this->insertTokens(User::class, $user->id, 3, 'scoped');
        $this->insertTokens('App\\Models\\Other', 5, 3, 'foreign');
        $deletes = [];
        DB::listen(function ($query) use (&$deletes): void {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'delete')) {
                $deletes[] = $query;
            }
        });

        $this->purge(['--confirm' => self::CONFIRM]);

        $this->assertNotEmpty($deletes);
        foreach ($deletes as $delete) {
            $this->assertStringContainsString('tokenable_type', $delete->sql);
            $this->assertContains(User::class, $delete->bindings);
        }
        $this->assertSame(3, DB::table('personal_access_tokens')->where('tokenable_type', 'App\\Models\\Other')->count());
    }

    public function test_empty_table_works_in_both_modes(): void
    {
        [$code, $output] = $this->purge();
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Tokens de User que se eliminarían: 0', $output);

        [$code, $output] = $this->purge(['--confirm' => self::CONFIRM]);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Tokens de User eliminados: 0 en 0 lotes', $output);
    }

    /** @return array<string, array{mixed, mixed}> */
    public static function unsafeConfigurations(): array
    {
        return [
            'issuance true / acceptance true' => [true, true],
            'issuance false / acceptance true' => [false, true],
            'issuance true / acceptance false' => [true, false],
            'non-boolean acceptance' => [false, 'flase'],
            'non-boolean issuance' => ['no', false],
            'null values' => [null, null],
        ];
    }

    #[DataProvider('unsafeConfigurations')]
    public function test_unsafe_configuration_refuses_confirmed_purge_and_dry_run(mixed $issuance, mixed $acceptance): void
    {
        $user = User::factory()->create();
        $this->insertTokens(User::class, $user->id, 4, 'safe');
        config()->set('legacy_bearer.issuance_enabled', $issuance);
        config()->set('legacy_bearer.acceptance_enabled', $acceptance);

        [$code, $output] = $this->purge(['--confirm' => self::CONFIRM]);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('No se ha consultado ni modificado ningún dato', $output);
        $this->assertStringNotContainsString('ELIMINACIÓN COMPLETADA', $output);

        [$dryCode] = $this->purge();
        $this->assertSame(1, $dryCode);
        $this->assertSame(4, $this->userTokens());
    }

    #[DataProvider('wrongConfirmations')]
    public function test_wrong_confirmation_cannot_delete(string $confirm): void
    {
        $user = User::factory()->create();
        $this->insertTokens(User::class, $user->id, 2, 'wrong');

        [$code, $output] = $this->purge(['--confirm' => $confirm]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Confirmación incorrecta', $output);
        $this->assertSame(2, $this->userTokens());
    }

    /** @return array<string, array{string}> */
    public static function wrongConfirmations(): array
    {
        return [
            'lower case' => ['purge-legacy-pats'],
            'truncated' => ['PURGE-LEGACY'],
            'padded' => [' PURGE-LEGACY-PATS'],
            'yes' => ['yes'],
            'empty' => [''],
        ];
    }

    #[DataProvider('invalidChunks')]
    public function test_invalid_chunk_size_is_rejected(string $chunk): void
    {
        $user = User::factory()->create();
        $this->insertTokens(User::class, $user->id, 2, 'chunk');

        [$code] = $this->purge(['--confirm' => self::CONFIRM, '--chunk' => $chunk]);

        $this->assertSame(1, $code);
        $this->assertSame(2, $this->userTokens());
    }

    /** @return array<string, array{string}> */
    public static function invalidChunks(): array
    {
        return ['zero' => ['0'], 'negative' => ['-5'], 'text' => ['abc'], 'too large' => ['10001'], 'decimal' => ['1.5']];
    }

    public function test_output_never_exposes_token_hashes_plaintext_or_identifiers(): void
    {
        $user = User::factory()->create(['email' => 'private-person@example.test']);
        $plain = $user->createToken('api-token')->plainTextToken;
        [, $secret] = explode('|', $plain, 2);
        $hash = DB::table('personal_access_tokens')->value('token');

        [, $dry] = $this->purge();
        [, $real] = $this->purge(['--confirm' => self::CONFIRM]);

        foreach ([$dry, $real] as $output) {
            $this->assertStringNotContainsString($secret, $output);
            $this->assertStringNotContainsString($hash, $output);
            $this->assertStringNotContainsString($plain, $output);
            $this->assertStringNotContainsString('private-person@example.test', $output);
        }
    }

    public function test_purge_removes_the_users_tokens_without_touching_the_user(): void
    {
        $user = User::factory()->create();
        $user->createToken('api-token');

        $this->purge(['--confirm' => self::CONFIRM]);

        $this->assertSame(0, $user->fresh()->tokens()->count());
        $this->assertNotNull(User::find($user->id));
    }
}
