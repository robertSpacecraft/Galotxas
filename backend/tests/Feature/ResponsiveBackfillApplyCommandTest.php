<?php

namespace Tests\Feature;

use App\Console\Commands\ResponsiveBackfillCommand;
use App\Services\Media\Backfill\ApplyInvocation;
use App\Services\Media\Backfill\ApplyOutcome;
use App\Services\Media\Backfill\ApplyReport;
use App\Services\Media\Backfill\ManagedMediaDomain;
use App\Services\Media\Backfill\PreflightClassification;
use App\Services\Media\Backfill\ResponsiveBackfillApply;
use App\Services\Media\Backfill\ResponsiveBackfillDryRun;
use App\Services\Media\Backfill\Safety\ApplyResult;
use App\Services\Media\Backfill\Safety\RunState;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class ResponsiveBackfillApplyCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('galotxas_testing', DB::connection()->getDatabaseName());
        Storage::fake('media_local');
    }

    public function test_help_exposes_apply_but_not_resume(): void
    {
        [$exitCode, $output] = $this->callRaw('--help');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('--apply', $output);
        $this->assertStringNotContainsString('--resume', $output);
    }

    public function test_pure_dry_run_unknown_option_keeps_normal_parser_failure(): void
    {
        $this->bindUnusedCoordinators();

        [$exitCode, $output] = $this->callRaw('--unknown-option=private-parser-value');

        $this->assertSame(1, $exitCode);
        $this->assertStringNotContainsString('Opciones APPLY no válidas.', $output);
    }

    public function test_valid_apply_defaults_after_id_calls_coordinator_once_and_warns_first(): void
    {
        $runner = Mockery::mock(ResponsiveBackfillDryRun::class);
        $runner->shouldNotReceive('run');
        $this->app->instance(ResponsiveBackfillDryRun::class, $runner);

        $output = new BufferedOutput;
        $warningBeforeCoordinator = null;
        $apply = Mockery::mock(ResponsiveBackfillApply::class);
        $apply->shouldReceive('run')
            ->once()
            ->with(Mockery::on(static fn (ApplyInvocation $invocation): bool => $invocation->domain === ManagedMediaDomain::News
                && $invocation->afterId === 0
                && $invocation->limit === 1))
            ->andReturnUsing(function () use ($output, &$warningBeforeCoordinator): ApplyReport {
                $warningBeforeCoordinator = $output->fetch();

                return $this->report(ApplyOutcome::Success, limit: 1, runState: RunState::Completed, checkpoint: 0);
            });
        $this->app->instance(ResponsiveBackfillApply::class, $apply);

        $exitCode = Artisan::call('media:responsive-backfill', [
            '--apply' => true,
            '--domain' => ['news'],
            '--limit' => '1',
        ], $output);
        $reportOutput = $output->fetch();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('ADVERTENCIA: el comando verifica el mantenimiento de Laravel', $warningBeforeCoordinator);
        $this->assertStringContainsString('workers u otros escritores externos', $warningBeforeCoordinator);
        $this->assertStringContainsString('El operador debe detenerlos antes de APPLY.', $warningBeforeCoordinator);
        $this->assertStringNotContainsString('workers verificados', $warningBeforeCoordinator);
        $this->assertStringContainsString('Modo: APPLY', $reportOutput);
        $this->assertStringNotContainsString('Modo: DRY-RUN', $reportOutput);
    }

    public function test_leading_zeroes_are_normalized_and_forwarded(): void
    {
        $runner = Mockery::mock(ResponsiveBackfillDryRun::class);
        $runner->shouldNotReceive('run');
        $this->app->instance(ResponsiveBackfillDryRun::class, $runner);

        $apply = Mockery::mock(ResponsiveBackfillApply::class);
        $apply->shouldReceive('run')
            ->once()
            ->with(Mockery::on(static fn (ApplyInvocation $invocation): bool => $invocation->domain === ManagedMediaDomain::Sponsor
                && $invocation->afterId === 12
                && $invocation->limit === 25))
            ->andReturn($this->report(
                ApplyOutcome::Success,
                ManagedMediaDomain::Sponsor,
                afterId: 12,
                limit: 25,
                runState: RunState::Completed,
                checkpoint: 12,
            ));
        $this->app->instance(ResponsiveBackfillApply::class, $apply);

        $this->assertSame(0, Artisan::call('media:responsive-backfill', [
            '--apply' => true,
            '--domain' => ['sponsor'],
            '--after-id' => '00012',
            '--limit' => '00025',
        ]));
    }

    #[DataProvider('canonicalDomains')]
    public function test_each_canonical_domain_is_accepted_individually(ManagedMediaDomain $domain): void
    {
        $runner = Mockery::mock(ResponsiveBackfillDryRun::class);
        $runner->shouldNotReceive('run');
        $this->app->instance(ResponsiveBackfillDryRun::class, $runner);

        $apply = Mockery::mock(ResponsiveBackfillApply::class);
        $apply->shouldReceive('run')
            ->once()
            ->with(Mockery::on(static fn (ApplyInvocation $invocation): bool => $invocation->domain === $domain
                && $invocation->afterId === 0
                && $invocation->limit === 1))
            ->andReturn($this->report(ApplyOutcome::Success, $domain, limit: 1, runState: RunState::Completed, checkpoint: 0));
        $this->app->instance(ResponsiveBackfillApply::class, $apply);

        $this->assertSame(0, Artisan::call('media:responsive-backfill', [
            '--apply' => true,
            '--domain' => [$domain->value],
            '--limit' => '1',
        ]));
    }

    public static function canonicalDomains(): iterable
    {
        foreach (ManagedMediaDomain::cases() as $domain) {
            yield $domain->value => [$domain];
        }
    }

    #[DataProvider('invalidApplyArguments')]
    public function test_invalid_apply_arguments_return_two_without_calling_either_runner(array $arguments): void
    {
        $this->bindUnusedCoordinators();

        $exitCode = Artisan::call('media:responsive-backfill', ['--apply' => true] + $arguments);
        $output = Artisan::output();

        $this->assertSame(2, $exitCode);
        $this->assertStringContainsString('Opciones APPLY no válidas.', $output);
        $this->assertStringNotContainsString('ADVERTENCIA:', $output);
        $this->assertStringNotContainsString('Stack trace', $output);
        $this->assertNoOperationalEvidence();
    }

    public static function invalidApplyArguments(): iterable
    {
        yield 'missing domain' => [['--limit' => '1']];
        yield 'multiple domains' => [['--domain' => ['news', 'avatar'], '--limit' => '1']];
        yield 'duplicate domain' => [['--domain' => ['news', 'news'], '--limit' => '1']];
        yield 'unknown domain' => [['--domain' => ['other'], '--limit' => '1']];
        yield 'missing limit' => [['--domain' => ['news']]];

        foreach (self::invalidLimitValues() as $name => $value) {
            yield 'limit '.$name => [['--domain' => ['news'], '--limit' => $value]];
        }
        foreach (self::invalidAfterIdValues() as $name => $value) {
            yield 'after-id '.$name => [[
                '--domain' => ['news'],
                '--limit' => '1',
                '--after-id' => $value,
            ]];
        }
    }

    private static function invalidLimitValues(): iterable
    {
        yield 'zero' => '0';
        yield 'over maximum' => '1001';
        yield 'negative' => '-1';
        yield 'positive sign' => '+1';
        yield 'decimal' => '1.0';
        yield 'scientific' => '1e2';
        yield 'leading whitespace' => ' 1';
        yield 'trailing whitespace' => '1 ';
        yield 'empty' => '';
    }

    private static function invalidAfterIdValues(): iterable
    {
        yield 'negative' => '-1';
        yield 'positive sign' => '+1';
        yield 'decimal' => '1.0';
        yield 'scientific' => '1e2';
        yield 'leading whitespace' => ' 1';
        yield 'trailing whitespace' => '1 ';
        yield 'empty' => '';
        yield 'over PHP integer maximum' => '9223372036854775808';
    }

    #[DataProvider('invalidRawApplyInvocations')]
    public function test_apply_related_raw_parser_errors_return_two_without_coordinator_call(
        string $arguments,
        ?string $sensitiveValue = null,
    ): void {
        $this->bindUnusedCoordinators();

        [$exitCode, $output] = $this->callRaw($arguments);

        $this->assertSame(2, $exitCode);
        $this->assertStringContainsString('Opciones APPLY no válidas.', $output);
        $this->assertStringNotContainsString('Stack trace', $output);
        if ($sensitiveValue !== null) {
            $this->assertStringNotContainsString($sensitiveValue, $output);
        }
        $this->assertNoOperationalEvidence();
    }

    public static function invalidRawApplyInvocations(): iterable
    {
        yield 'forbidden resume alone' => ['--resume'];
        yield 'forbidden resume with apply' => ['--apply --resume --domain=news --limit=1'];
        yield 'unknown option with apply' => ['--apply --unknown-option=private-parser-value --domain=news --limit=1', 'private-parser-value'];
        yield 'value joined to apply flag' => ['--apply=private-parser-value --domain=news --limit=1', 'private-parser-value'];
        yield 'value after apply flag' => ['--apply private-parser-value --domain=news --limit=1', 'private-parser-value'];
    }

    #[DataProvider('applyOutcomes')]
    public function test_apply_outcome_maps_to_exact_exit_code(ApplyOutcome $outcome, int $expectedExitCode): void
    {
        $runner = Mockery::mock(ResponsiveBackfillDryRun::class);
        $runner->shouldNotReceive('run');
        $this->app->instance(ResponsiveBackfillDryRun::class, $runner);

        $apply = Mockery::mock(ResponsiveBackfillApply::class);
        $apply->shouldReceive('run')->once()->andReturn($this->report($outcome, limit: 1));
        $this->app->instance(ResponsiveBackfillApply::class, $apply);

        $exitCode = Artisan::call('media:responsive-backfill', [
            '--apply' => true,
            '--domain' => ['news'],
            '--limit' => '1',
        ]);

        $this->assertSame($expectedExitCode, $exitCode);
        $this->assertStringContainsString('Resultado: '.$outcome->value, Artisan::output());
    }

    public static function applyOutcomes(): iterable
    {
        yield 'success' => [ApplyOutcome::Success, 0];
        yield 'first pass blocked' => [ApplyOutcome::FirstPassBlocked, 2];
        yield 'maintenance required' => [ApplyOutcome::MaintenanceRequired, 3];
        yield 'lock busy' => [ApplyOutcome::LockBusy, 4];
        yield 'lock acquire failed' => [ApplyOutcome::LockAcquireFailed, 5];
        yield 'safe failure' => [ApplyOutcome::SafeFailure, 6];
        yield 'reconciliation required' => [ApplyOutcome::ReconciliationRequired, 7];
    }

    public function test_apply_report_renders_only_safe_fields_in_canonical_count_order_and_nullable_values(): void
    {
        $classificationCounts = [];
        foreach (PreflightClassification::cases() as $index => $classification) {
            $classificationCounts[$classification->value] = $index;
        }
        $resultCounts = [];
        foreach (ApplyResult::cases() as $index => $result) {
            $resultCounts[$result->value] = $index;
        }
        $report = new ApplyReport(
            ApplyOutcome::MaintenanceRequired,
            ManagedMediaDomain::News,
            12,
            null,
            25,
            4,
            $classificationCounts,
            $resultCounts,
            null,
            null,
        );
        $this->bindReport($report);

        $this->assertSame(3, Artisan::call('media:responsive-backfill', [
            '--apply' => true,
            '--domain' => ['news'],
            '--after-id' => '12',
            '--limit' => '25',
        ]));
        $output = Artisan::output();

        $this->assertStringContainsString('Modo: APPLY', $output);
        $this->assertStringContainsString('Dominio: news', $output);
        $this->assertStringContainsString('After-id: 12', $output);
        $this->assertStringContainsString('Límite superior: -', $output);
        $this->assertStringContainsString('Límite: 25', $output);
        $this->assertStringContainsString('Referencias observadas: 4', $output);
        $this->assertStringContainsString('Clasificaciones: '.$this->expectedClassificationCounts($classificationCounts), $output);
        $this->assertStringContainsString('Resultados: '.$this->expectedResultCounts($resultCounts), $output);
        $this->assertStringContainsString('Checkpoint: -', $output);
        $this->assertStringContainsString('Estado del run: -', $output);
        $this->assertStringContainsString('Reconciliación requerida: no', $output);
        $this->assertStringContainsString('Resultado: maintenance_required', $output);
        $this->assertStringNotContainsString('Escrituras en storage:', $output);

        foreach ([
            'private-run-uuid',
            'private/object/key.webp',
            'schema_version',
            'private-sha-256',
            'storage_identity',
            'galotxas:media:bf:',
            'private-bucket',
            'private-credential',
        ] as $sensitiveValue) {
            $this->assertStringNotContainsString($sensitiveValue, $output);
        }
    }

    public function test_reconciliation_report_is_explicit_and_has_no_continuation(): void
    {
        $this->bindReport($this->report(
            ApplyOutcome::ReconciliationRequired,
            afterId: 12,
            upperBound: 50,
            limit: 25,
            observedCount: 3,
            runState: RunState::Interrupted,
            checkpoint: 12,
        ));

        $this->assertSame(7, Artisan::call('media:responsive-backfill', [
            '--apply' => true,
            '--domain' => ['news'],
            '--after-id' => '12',
            '--limit' => '25',
        ]));
        $output = Artisan::output();

        $this->assertStringContainsString('Reconciliación requerida: sí', $output);
        $this->assertStringContainsString('se requiere D2/reconciliación', $output);
        $this->assertStringNotContainsString('Nueva invocación sugerida:', $output);
    }

    public function test_clean_completed_success_renders_new_manual_invocation_hint(): void
    {
        $this->bindReport($this->report(
            ApplyOutcome::Success,
            afterId: 0,
            upperBound: 50,
            limit: 25,
            observedCount: 25,
            runState: RunState::Completed,
            checkpoint: 12,
            continuationAfterId: 12,
        ));

        $this->assertSame(0, Artisan::call('media:responsive-backfill', [
            '--apply' => true,
            '--domain' => ['news'],
            '--limit' => '25',
        ]));
        $output = Artisan::output();

        $this->assertStringContainsString(
            'Nueva invocación sugerida: --apply --domain=news --after-id=12 --limit=25',
            $output,
        );
        $this->assertStringNotContainsString('resume', strtolower($output));
    }

    public function test_non_success_report_never_renders_continuation(): void
    {
        $this->bindReport($this->report(
            ApplyOutcome::SafeFailure,
            upperBound: 50,
            limit: 25,
            observedCount: 4,
            runState: RunState::Failed,
            checkpoint: 4,
        ));

        $this->assertSame(6, Artisan::call('media:responsive-backfill', [
            '--apply' => true,
            '--domain' => ['news'],
            '--limit' => '25',
        ]));
        $this->assertStringNotContainsString('Nueva invocación sugerida:', Artisan::output());
    }

    public function test_unexpected_apply_throwable_is_not_retried_and_fails_closed_without_details(): void
    {
        $runner = Mockery::mock(ResponsiveBackfillDryRun::class);
        $runner->shouldNotReceive('run');
        $this->app->instance(ResponsiveBackfillDryRun::class, $runner);

        $apply = Mockery::mock(ResponsiveBackfillApply::class);
        $apply->shouldReceive('run')->once()->andThrow(new RuntimeException('private credential and object key'));
        $this->app->instance(ResponsiveBackfillApply::class, $apply);

        $this->assertSame(7, Artisan::call('media:responsive-backfill', [
            '--apply' => true,
            '--domain' => ['news'],
            '--limit' => '1',
        ]));
        $output = Artisan::output();

        $this->assertStringContainsString('APPLY no pudo completarse de forma segura.', $output);
        $this->assertStringContainsString('revisión y reconciliación', $output);
        $this->assertStringNotContainsString('private credential and object key', $output);
        $this->assertStringNotContainsString('Modo: APPLY', $output);
    }

    public function test_command_has_no_direct_storage_journal_lock_delete_retry_or_resume_capability(): void
    {
        $source = file_get_contents(app_path('Console/Commands/ResponsiveBackfillCommand.php'));
        $command = $this->app->make(ResponsiveBackfillCommand::class);

        $this->assertStringContainsString('ResponsiveBackfillApply', $source);
        $this->assertStringNotContainsString('ApplyJournal', $source);
        $this->assertStringNotContainsString('ApplyItemPublisher', $source);
        $this->assertStringNotContainsString('MariaDbBackfillLock', $source);
        $this->assertStringNotContainsString('JournaledObjectWriter', $source);
        $this->assertStringNotContainsString('ExclusiveObjectCreator', $source);
        $this->assertStringNotContainsString('Storage::', $source);
        $this->assertStringNotContainsString('delete(', $source);
        $this->assertStringNotContainsString('retry(', $source);
        $this->assertTrue($command->getDefinition()->hasOption('apply'));
        $this->assertFalse($command->getDefinition()->hasOption('resume'));
    }

    private function bindUnusedCoordinators(): void
    {
        $runner = Mockery::mock(ResponsiveBackfillDryRun::class);
        $runner->shouldNotReceive('run');
        $this->app->instance(ResponsiveBackfillDryRun::class, $runner);

        $apply = Mockery::mock(ResponsiveBackfillApply::class);
        $apply->shouldNotReceive('run');
        $this->app->instance(ResponsiveBackfillApply::class, $apply);
    }

    private function bindReport(ApplyReport $report): void
    {
        $runner = Mockery::mock(ResponsiveBackfillDryRun::class);
        $runner->shouldNotReceive('run');
        $this->app->instance(ResponsiveBackfillDryRun::class, $runner);

        $apply = Mockery::mock(ResponsiveBackfillApply::class);
        $apply->shouldReceive('run')->once()->andReturn($report);
        $this->app->instance(ResponsiveBackfillApply::class, $apply);
    }

    private function report(
        ApplyOutcome $outcome,
        ManagedMediaDomain $domain = ManagedMediaDomain::News,
        int $afterId = 0,
        ?int $upperBound = 0,
        int $limit = 1000,
        int $observedCount = 0,
        ?RunState $runState = null,
        ?int $checkpoint = null,
        ?int $continuationAfterId = null,
    ): ApplyReport {
        return new ApplyReport(
            $outcome,
            $domain,
            $afterId,
            $upperBound,
            $limit,
            $observedCount,
            array_fill_keys(array_column(PreflightClassification::cases(), 'value'), 0),
            array_fill_keys(array_column(ApplyResult::cases(), 'value'), 0),
            $checkpoint,
            $runState,
            $continuationAfterId,
        );
    }

    /** @return array{int, string} */
    private function callRaw(string $arguments): array
    {
        $input = new StringInput('media:responsive-backfill'.($arguments === '' ? '' : ' '.$arguments));
        $output = new BufferedOutput;
        $exitCode = $this->app->make(ConsoleKernel::class)->handle($input, $output);

        return [$exitCode, $output->fetch()];
    }

    /** @param array<string, int> $counts */
    private function expectedClassificationCounts(array $counts): string
    {
        return implode(', ', array_map(
            static fn (PreflightClassification $classification): string => $classification->value.'='.$counts[$classification->value],
            PreflightClassification::cases(),
        ));
    }

    /** @param array<string, int> $counts */
    private function expectedResultCounts(array $counts): string
    {
        return implode(', ', array_map(
            static fn (ApplyResult $result): string => $result->value.'='.$counts[$result->value],
            ApplyResult::cases(),
        ));
    }

    private function assertNoOperationalEvidence(): void
    {
        $this->assertSame(0, DB::table('media_backfill_runs')->count());
        $this->assertSame(0, DB::table('media_backfill_items')->count());
        $this->assertSame(0, DB::table('media_backfill_objects')->count());
        $this->assertSame([], Storage::disk('media_local')->allFiles());
    }
}
