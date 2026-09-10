<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Media\Backfill\DryRunReport;
use App\Services\Media\Backfill\ManagedMediaDomain;
use App\Services\Media\Backfill\ManagedMediaReferenceRegistry;
use App\Services\Media\Backfill\PreflightClassification;
use App\Services\Media\Backfill\ResponsiveBackfillDryRun;
use App\Services\Media\Backfill\ResponsiveBackfillPreflight;
use App\Services\Media\ExistingMasterPreparer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\InspectsMemoryMedia;
use Tests\TestCase;
use Tests\Unit\Media\ResponsiveImageFixtures;

class ResponsiveBackfillCommandTest extends TestCase
{
    use InspectsMemoryMedia;
    use RefreshDatabase;
    use ResponsiveImageFixtures;

    public function test_command_is_registered_and_empty_dry_run_is_clean(): void
    {
        $exitCode = Artisan::call('media:responsive-backfill');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Modo: DRY-RUN', $output);
        $this->assertStringContainsString('Dominios: avatar, news, sponsor, season, championship, category', $output);
        $this->assertStringContainsString('Límites superiores: avatar=0, news=0, sponsor=0, season=0, championship=0, category=0', $output);
        $this->assertStringContainsString('Referencias observadas: 0', $output);
        $this->assertStringContainsString('Escrituras en storage: 0', $output);
        $this->assertSame(0, DB::table('media_backfill_runs')->count());
        $this->assertSame(0, DB::table('media_backfill_items')->count());
        $this->assertSame(0, DB::table('media_backfill_objects')->count());
    }

    public function test_repeated_domains_are_passed_to_runner_in_canonical_order(): void
    {
        $expected = [ManagedMediaDomain::Avatar, ManagedMediaDomain::News, ManagedMediaDomain::Category];
        $runner = Mockery::mock(ResponsiveBackfillDryRun::class);
        $runner->shouldReceive('run')->once()->with($expected, 0, null)->andReturn($this->report($expected));
        $this->app->instance(ResponsiveBackfillDryRun::class, $runner);

        $exitCode = Artisan::call('media:responsive-backfill', [
            '--domain' => ['category', 'avatar', 'news'],
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Dominios: avatar, news, category', Artisan::output());
    }

    public function test_unexpected_runner_failure_returns_one_without_exposing_details(): void
    {
        $runner = Mockery::mock(ResponsiveBackfillDryRun::class);
        $runner->shouldReceive('run')->once()->andThrow(new RuntimeException('private endpoint credentials'));
        $this->app->instance(ResponsiveBackfillDryRun::class, $runner);

        $this->assertSame(1, Artisan::call('media:responsive-backfill'));
        $output = Artisan::output();
        $this->assertStringContainsString('No se pudo completar la inspección read-only.', $output);
        $this->assertStringContainsString('Escrituras en storage: 0', $output);
        $this->assertStringNotContainsString('private endpoint credentials', $output);
        $this->assertStringNotContainsString('Modo: DRY-RUN', $output);
    }

    #[DataProvider('invalidDomains')]
    public function test_duplicate_and_unknown_domains_are_rejected(array $domains): void
    {
        $runner = Mockery::mock(ResponsiveBackfillDryRun::class);
        $runner->shouldNotReceive('run');
        $this->app->instance(ResponsiveBackfillDryRun::class, $runner);

        $this->assertSame(2, Artisan::call('media:responsive-backfill', ['--domain' => $domains]));
        $this->assertStringNotContainsString('Stack trace', Artisan::output());
    }

    public static function invalidDomains(): iterable
    {
        yield 'duplicate' => [['avatar', 'avatar']];
        yield 'unknown' => [['avatar', 'other']];
    }

    #[DataProvider('invalidAfterIds')]
    public function test_after_id_is_strictly_validated(mixed $afterId): void
    {
        $runner = Mockery::mock(ResponsiveBackfillDryRun::class);
        $runner->shouldNotReceive('run');
        $this->app->instance(ResponsiveBackfillDryRun::class, $runner);

        $this->assertSame(2, Artisan::call('media:responsive-backfill', [
            '--domain' => ['avatar'],
            '--after-id' => $afterId,
        ]));
    }

    public static function invalidAfterIds(): iterable
    {
        yield 'negative' => ['-1'];
        yield 'positive sign' => ['+1'];
        yield 'decimal' => ['1.0'];
        yield 'leading whitespace' => [' 1'];
        yield 'trailing whitespace' => ['1 '];
        yield 'scientific' => ['1e2'];
        yield 'empty' => [''];
    }

    public function test_after_id_requires_exactly_one_domain_even_when_zero(): void
    {
        $runner = Mockery::mock(ResponsiveBackfillDryRun::class);
        $runner->shouldNotReceive('run');
        $this->app->instance(ResponsiveBackfillDryRun::class, $runner);

        $this->assertSame(2, Artisan::call('media:responsive-backfill', ['--after-id' => '0']));
    }

    #[DataProvider('invalidLimits')]
    public function test_limit_is_strictly_validated(mixed $limit): void
    {
        $runner = Mockery::mock(ResponsiveBackfillDryRun::class);
        $runner->shouldNotReceive('run');
        $this->app->instance(ResponsiveBackfillDryRun::class, $runner);

        $this->assertSame(2, Artisan::call('media:responsive-backfill', ['--limit' => $limit]));
    }

    public static function invalidLimits(): iterable
    {
        yield 'zero' => ['0'];
        yield 'over maximum' => ['1001'];
        yield 'negative' => ['-1'];
        yield 'positive sign' => ['+1'];
        yield 'decimal' => ['1.0'];
        yield 'whitespace' => [' 1'];
        yield 'scientific' => ['1e2'];
        yield 'empty' => [''];
    }

    public function test_valid_after_id_and_limit_are_forwarded(): void
    {
        $domains = [ManagedMediaDomain::Sponsor];
        $runner = Mockery::mock(ResponsiveBackfillDryRun::class);
        $runner->shouldReceive('run')->once()->with($domains, 12, 1000)->andReturn($this->report($domains));
        $this->app->instance(ResponsiveBackfillDryRun::class, $runner);

        $this->assertSame(0, Artisan::call('media:responsive-backfill', [
            '--domain' => ['sponsor'],
            '--after-id' => '0012',
            '--limit' => '1000',
        ]));
    }

    public function test_late_blocker_finishes_scan_without_persistent_writes_or_sensitive_output(): void
    {
        $validKey = 'avatars/550e8400-e29b-41d4-a716-446655440000.webp';
        User::factory()->create(['profile_photo_path' => $validKey]);
        $invalid = User::factory()->create(['profile_photo_path' => '../private-secret']);
        $before = User::query()->pluck('profile_photo_path', 'id')->all();
        $preflight = new ResponsiveBackfillPreflight(
            app(ManagedMediaReferenceRegistry::class),
            $this->memoryInspector([$validKey => $this->fixtureBytes(400, 200, 'webp')]),
            app(ExistingMasterPreparer::class),
        );
        $this->app->instance(ResponsiveBackfillPreflight::class, $preflight);
        $queries = [];
        DB::listen(static function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $exitCode = Artisan::call('media:responsive-backfill', ['--domain' => ['avatar']]);
        $output = Artisan::output();

        $this->assertSame(2, $exitCode);
        $this->assertStringContainsString('legacy_backfillable=1', $output);
        $this->assertStringContainsString('invalid_reference=1', $output);
        $this->assertStringContainsString('Anomalía: avatar#'.$invalid->id.' invalid_reference', $output);
        $this->assertStringContainsString('Escrituras en storage: 0', $output);
        $this->assertStringNotContainsString('../private-secret', $output);
        $this->assertStringNotContainsString('schema_version', $output);
        $this->assertNotEmpty($queries);
        $this->assertTrue(collect($queries)->every(
            static fn (string $sql): bool => preg_match('/^select\b/i', $sql) === 1,
        ));
        $this->assertSame($before, User::query()->pluck('profile_photo_path', 'id')->all());
        $this->assertSame(0, DB::table('media_backfill_runs')->count());
        $this->assertSame(0, DB::table('media_backfill_items')->count());
        $this->assertSame(0, DB::table('media_backfill_objects')->count());
    }

    /** @param list<ManagedMediaDomain> $domains */
    private function report(array $domains): DryRunReport
    {
        return new DryRunReport(
            $domains,
            array_fill_keys(array_column($domains, 'value'), 0),
            0,
            array_fill_keys(array_map(
                static fn ($classification): string => $classification->value,
                PreflightClassification::cases(),
            ), 0),
            0,
            0,
            [],
            0,
            false,
            null,
            null,
        );
    }
}
