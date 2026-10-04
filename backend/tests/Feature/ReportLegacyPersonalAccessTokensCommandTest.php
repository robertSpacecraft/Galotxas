<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithUserCredentials;
use Tests\TestCase;

class ReportLegacyPersonalAccessTokensCommandTest extends TestCase
{
    use InteractsWithUserCredentials;
    use RefreshDatabase;

    private const CUTOFF = '2026-10-03T14:54:46Z';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-10 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function token(User $user, string $name, ?string $createdAt, ?string $lastUsedAt): string
    {
        $accessToken = $user->createToken($name);

        DB::table('personal_access_tokens')->where('id', $accessToken->accessToken->id)->update([
            'created_at' => $createdAt,
            'last_used_at' => $lastUsedAt,
        ]);

        return $accessToken->plainTextToken;
    }

    /**
     * @return array<string, string>
     */
    private function seedTokens(): array
    {
        $admin = User::factory()->admin()->create();
        $player = User::factory()->create();
        $inactive = User::factory()->create(['active' => false]);

        return [
            $this->token($admin, 'api-token', '2026-09-01 10:00:00', null),
            $this->token($player, 'api-token', '2026-09-20 10:00:00', '2026-10-10 09:00:00'),
            $this->token($player, 'api-token', '2026-10-05 10:00:00', '2026-10-05 10:00:00'),
            $this->token($inactive, 'other-device', '2026-10-04 10:00:00', '2026-09-25 10:00:00'),
            $this->token($inactive, 'api-token', '2026-08-01 10:00:00', '2026-08-15 10:00:00'),
        ];
    }

    private function report(array $options = []): array
    {
        $code = Artisan::call('auth:legacy-pat-report', $options);

        return [$code, Artisan::output()];
    }

    private function row(string $output, string $label): int
    {
        $this->assertMatchesRegularExpression('/\|\s*'.preg_quote($label, '/').'\s*\|\s*(\d+)\s*\|/u', $output);
        preg_match('/\|\s*'.preg_quote($label, '/').'\s*\|\s*(\d+)\s*\|/u', $output, $matches);

        return (int) $matches[1];
    }

    public function test_reports_totals_and_null_vs_used_counts(): void
    {
        $this->seedTokens();

        [$code, $output] = $this->report();

        $this->assertSame(0, $code);
        $this->assertSame(5, $this->row($output, 'Tokens con tokenable User'));
        $this->assertSame(1, $this->row($output, 'last_used_at = null'));
        $this->assertSame(4, $this->row($output, 'last_used_at != null'));
        $this->assertStringNotContainsString('Creados desde el corte', $output);
    }

    public function test_created_since_and_used_since_are_distinct(): void
    {
        $this->seedTokens();

        [$code, $output] = $this->report(['--since' => self::CUTOFF]);

        $this->assertSame(0, $code);
        // Creados tras el corte: 2 (05-10 y 04-10). Usados tras el corte: 2 (10-10 y 05-10).
        $this->assertSame(2, $this->row($output, 'Creados desde el corte'));
        $this->assertSame(2, $this->row($output, 'Usados desde el corte'));
        $this->assertStringContainsString('2026-10-03T14:54:46Z', $output);
    }

    public function test_since_accepts_offsets_and_normalizes_to_utc(): void
    {
        $this->seedTokens();

        [$code, $output] = $this->report(['--since' => '2026-10-04T11:30:00+02:00']);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('2026-10-04T09:30:00Z', $output);
        $this->assertSame(2, $this->row($output, 'Creados desde el corte'));
    }

    public function test_aggregates_by_user_state_role_name_and_age(): void
    {
        $this->seedTokens();

        [, $output] = $this->report();

        $this->assertSame(3, $this->row($output, 'activo'));
        $this->assertSame(2, $this->row($output, 'inactivo'));
        $this->assertSame(1, $this->row($output, 'admin'));
        $this->assertSame(4, $this->row($output, 'user'));
        $this->assertSame(4, $this->row($output, 'api-token'));
        $this->assertSame(1, $this->row($output, 'other-device'));
        $this->assertSame(1, $this->row($output, 'last_used_at hace ≤ 24 h'));
        $this->assertSame(1, $this->row($output, 'last_used_at hace > 24 h y ≤ 7 d'));
        $this->assertSame(1, $this->row($output, 'last_used_at hace > 7 d y ≤ 30 d'));
        $this->assertSame(1, $this->row($output, 'last_used_at hace > 30 d'));
    }

    public function test_tokens_of_other_tokenable_types_and_orphans_are_handled(): void
    {
        $user = User::factory()->create();
        $this->token($user, 'api-token', '2026-10-05 10:00:00', null);
        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => 'App\\Models\\Other',
            'tokenable_id' => 999,
            'name' => 'foreign',
            'token' => str_repeat('a', 64),
            'abilities' => '["*"]',
            'created_at' => '2026-10-05 10:00:00',
            'updated_at' => '2026-10-05 10:00:00',
        ]);
        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => User::class,
            'tokenable_id' => 424242,
            'name' => 'api-token',
            'token' => str_repeat('b', 64),
            'abilities' => '["*"]',
            'created_at' => '2026-10-05 10:00:00',
            'updated_at' => '2026-10-05 10:00:00',
        ]);

        [, $output] = $this->report();

        $this->assertSame(2, $this->row($output, 'Tokens con tokenable User'));
        $this->assertSame(1, $this->row($output, 'sin usuario'));
        $this->assertStringNotContainsString('foreign', $output);
    }

    public function test_empty_table_reports_zeros(): void
    {
        [$code, $output] = $this->report(['--since' => self::CUTOFF]);

        $this->assertSame(0, $code);
        $this->assertSame(0, $this->row($output, 'Tokens con tokenable User'));
        $this->assertSame(0, $this->row($output, 'last_used_at = null'));
        $this->assertSame(0, $this->row($output, 'last_used_at != null'));
        $this->assertSame(0, $this->row($output, 'Creados desde el corte'));
        $this->assertSame(0, $this->row($output, 'Usados desde el corte'));
        $this->assertStringContainsString('Informe completado', $output);
    }

    #[DataProvider('invalidSinceProvider')]
    public function test_invalid_since_fails_without_querying_data(string $value): void
    {
        $this->seedTokens();

        [$code, $output] = $this->report(['--since' => $value]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('--since no válido', $output);
        $this->assertStringNotContainsString('Tokens con tokenable User', $output);
    }

    public static function invalidSinceProvider(): array
    {
        return [
            'text' => ['ayer'],
            'date only' => ['2026-10-03'],
            'no timezone' => ['2026-10-03T14:54:46'],
            'impossible date' => ['2026-02-31T10:00:00Z'],
            'bad hour' => ['2026-10-03T25:00:00Z'],
            'empty' => [''],
            'trailing junk' => ['2026-10-03T14:54:46Zjunk'],
        ];
    }

    public function test_command_is_read_only_and_leaks_no_secrets(): void
    {
        $plain = $this->seedTokens();
        $user = User::factory()->create(['email' => 'secret-person@example.test']);
        $this->createDurableSession($user);

        $snapshot = fn (): array => [
            DB::table('personal_access_tokens')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            DB::table('users')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            DB::table('sessions')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
        ];
        $before = $snapshot();

        [, $output] = $this->report(['--since' => self::CUTOFF]);

        $this->assertSame($before, $snapshot());

        foreach ($plain as $plainText) {
            [, $secret] = explode('|', $plainText, 2);
            $this->assertStringNotContainsString($secret, $output);
            $this->assertStringNotContainsString(hash('sha256', $secret), $output);
        }

        foreach (DB::table('personal_access_tokens')->pluck('token') as $hash) {
            $this->assertStringNotContainsString($hash, $output);
        }

        $this->assertStringNotContainsString('secret-person@example.test', $output);
        $this->assertStringNotContainsString('127.0.0.1', $output);
        $this->assertStringNotContainsString('PHPUnit', $output);
    }
}
