<?php

namespace Tests\Feature;

use App\Console\Commands\ResponsiveBackfillReconcileCommand;
use App\Services\Media\Backfill\ApplyItemPublisher;
use App\Services\Media\Backfill\ManagedMediaDomain;
use App\Services\Media\Backfill\ManagedMediaReference;
use App\Services\Media\Backfill\PreflightClassification;
use App\Services\Media\Backfill\PreflightResult;
use App\Services\Media\Backfill\Reconciliation\ExactObjectObserver;
use App\Services\Media\Backfill\Reconciliation\ItemClassification;
use App\Services\Media\Backfill\Reconciliation\ItemReconciliationReport;
use App\Services\Media\Backfill\Reconciliation\ObjectAttribution;
use App\Services\Media\Backfill\Reconciliation\ObjectClassification;
use App\Services\Media\Backfill\Reconciliation\ObjectObservation;
use App\Services\Media\Backfill\Reconciliation\ObjectReconciliationReport;
use App\Services\Media\Backfill\Reconciliation\ReconciliationInspector;
use App\Services\Media\Backfill\Reconciliation\RunFlag;
use App\Services\Media\Backfill\Safety\ApplyJournal;
use App\Services\Media\Backfill\Safety\ApplyMaintenanceGuard;
use App\Services\Media\Backfill\Safety\ApplyResult;
use App\Services\Media\Backfill\Safety\CleanupState;
use App\Services\Media\Backfill\Safety\CreateReceipt;
use App\Services\Media\Backfill\Safety\CreateState;
use App\Services\Media\Backfill\Safety\ExclusiveObjectCreator;
use App\Services\Media\Backfill\Safety\JournaledObjectWriter;
use App\Services\Media\Backfill\Safety\MariaDbBackfillLock;
use App\Services\Media\Backfill\Safety\ObjectKind;
use App\Services\Media\Backfill\Safety\RecoveryBarrierState;
use App\Services\Media\Backfill\Safety\RunState;
use App\Services\Media\Backfill\Safety\TargetObject;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Concerns\BackfillSafetyFixtures;
use Tests\TestCase;

class BackfillReconciliationInspectorTest extends TestCase
{
    use BackfillSafetyFixtures;
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('galotxas_testing', DB::connection()->getDatabaseName());
        $this->assertSame('test-db', DB::connection()->getConfig('host'));
        $this->setupSafetyStorage();
    }

    protected function tearDown(): void
    {
        try {
            $this->truncateTablesForAllConnections();
            $this->cleanupSafetyStorage();
        } finally {
            parent::tearDown();
        }
    }

    public function test_publication_unknown_exact_complete_set_is_only_a_forward_candidate_and_command_has_zero_side_effects(): void
    {
        [$journal, $runId, $itemId, $objects, $bytes] = $this->candidatePlan();
        foreach ($objects as $kind => $objectId) {
            $journal->commitIntent($objectId);
            $journal->recordReceipt($objectId, $kind === 'manifest'
                ? new CreateReceipt(CreateState::Unknown)
                : new CreateReceipt(CreateState::Created, 'private-etag', 'private-version'));
            Storage::disk('media_local')->put($bytes[$objectId]['key'], $bytes[$objectId]['bytes']);
        }
        $journal->finishItem($itemId, ApplyResult::PublicationUnknown);
        $journal->finishRun($runId, RunState::Interrupted);
        Storage::disk('media_local')->put('news/unrelated-preserved-master.jpg', 'preserved');

        $beforeRows = $this->journalSnapshot();
        $beforeFiles = $this->storageSnapshot();
        $beforeBarrier = $journal->recoveryBarrier();
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            if (preg_match('/media_backfill_(runs|items|objects)/i', $query->sql) === 1) {
                $queries[] = $query->sql;
            }
        });

        $exitCode = Artisan::call('media:responsive-backfill-reconcile', ['--item' => (string) $itemId]);
        $output = Artisan::output();
        $report = app(ReconciliationInspector::class)->inspectItem($itemId);

        $this->assertSame(0, $exitCode);
        $this->assertSame(ItemClassification::StorageSetExactDomainRevalidationPending, $report->classification);
        $this->assertSame(ApplyResult::PublicationUnknown, $report->applyResult);
        $this->assertTrue($report->domainRevalidationPending);
        $this->assertStringContainsString('storage_set_exact_domain_revalidation_pending_d2_c', $output);
        $this->assertStringContainsString('has_etag=yes', $output);
        $this->assertStringContainsString('has_version_identity=yes', $output);
        $this->assertStringNotContainsString('private-etag', $output);
        $this->assertStringNotContainsString('private-version', $output);
        foreach ($bytes as $expected) {
            $this->assertStringNotContainsString($expected['key'], $output);
        }
        $this->assertStringNotContainsString($this->safetyRoot, $output);
        $this->assertSame($beforeRows, $this->journalSnapshot());
        $this->assertSame($beforeFiles, $this->storageSnapshot());
        $this->assertSame($beforeBarrier, $journal->recoveryBarrier());
        $this->assertSame(RecoveryBarrierState::Blocked, $beforeBarrier);
        $this->assertSame([], array_values(array_filter(
            $queries,
            static fn (string $sql): bool => preg_match('/\A\s*(insert|update|delete|replace|alter|create|drop|truncate)\b/i', $sql) === 1,
        )));
    }

    public function test_ambiguous_absence_remains_observational_and_recovery_blocking(): void
    {
        [$journal, $runId, $itemId, $objects] = $this->candidatePlan();
        $variantId = $objects['variant'];
        $journal->commitIntent($variantId);

        $before = $this->journalSnapshot();
        $report = app(ReconciliationInspector::class)->inspectObject($variantId);

        $this->assertSame(ObjectObservation::AbsentNow, $report->observation);
        $this->assertSame(ObjectClassification::AmbiguousAbsentNow, $report->classification);
        $this->assertTrue($report->recoveryBlocker());
        $this->assertSame(RecoveryBarrierState::Blocked, $journal->recoveryBarrier());
        $this->assertSame($before, $this->journalSnapshot());
        $this->assertSame('intent', $journal->object($variantId)->write_state);
        $this->assertSame('writing', $journal->item($itemId)->phase);
        $this->assertSame('active', $journal->run($runId)->state);
    }

    public function test_exact_manifest_without_every_exact_variant_is_blocked(): void
    {
        [$journal, , $itemId, $objects, $bytes] = $this->candidatePlan();
        foreach ($objects as $objectId) {
            $journal->commitIntent($objectId);
            $journal->recordReceipt($objectId, new CreateReceipt(CreateState::Unknown));
        }
        $manifestId = $objects['manifest'];
        Storage::disk('media_local')->put($bytes[$manifestId]['key'], $bytes[$manifestId]['bytes']);

        $report = app(ReconciliationInspector::class)->inspectItem($itemId);

        $this->assertSame(ItemClassification::AmbiguousBlocked, $report->classification);
        $this->assertSame(ObjectObservation::ExpectedContentPresent, $report->manifest->observation);
        $this->assertContains(ObjectObservation::AbsentNow, array_map(
            static fn ($object) => $object->observation,
            array_filter($report->objects, static fn ($object): bool => $object->kind === ObjectKind::Variant),
        ));
    }

    public function test_exact_unknown_variant_with_absent_manifest_is_not_a_forward_complete_set(): void
    {
        [$journal, , $itemId, $objects, $bytes] = $this->candidatePlan();
        foreach ($objects as $objectId) {
            $journal->commitIntent($objectId);
            $journal->recordReceipt($objectId, new CreateReceipt(CreateState::Unknown));
        }
        $variantId = $objects['variant'];
        Storage::disk('media_local')->put($bytes[$variantId]['key'], $bytes[$variantId]['bytes']);

        $report = app(ReconciliationInspector::class)->inspectItem($itemId);

        $this->assertSame(ItemClassification::AmbiguousBlocked, $report->classification);
        $this->assertSame(ObjectObservation::AbsentNow, $report->manifest->observation);
        $this->assertSame(ObjectClassification::AmbiguousExpectedPresent, $this->objectReport($report, $variantId)->classification);
    }

    #[DataProvider('terminalNoWriteReceiptCases')]
    public function test_current_exact_functional_set_is_independent_of_historical_no_write_attribution(
        CreateState $receipt,
        ObjectAttribution $attribution,
        ObjectClassification $objectClassification,
    ): void {
        [$journal, , $itemId, $objects, $bytes] = $this->candidatePlan();
        $variantId = $objects['variant'];
        $journal->commitIntent($variantId);
        $journal->recordReceipt($variantId, new CreateReceipt($receipt));
        foreach ($bytes as $expected) {
            Storage::disk('media_local')->put($expected['key'], $expected['bytes']);
        }
        $before = $this->journalSnapshot();

        $report = app(ReconciliationInspector::class)->inspectItem($itemId);
        $variant = $this->objectReport($report, $variantId);

        $this->assertSame(ItemClassification::StorageSetExactDomainRevalidationPending, $report->classification);
        $this->assertSame($attribution, $variant->attribution);
        $this->assertSame($objectClassification, $variant->classification);
        $this->assertSame($receipt->value, $journal->object($variantId)->create_state);
        $this->assertSame('rejected', $journal->object($variantId)->write_state);
        $this->assertSame(ObjectAttribution::NotDispatched, $report->manifest->attribution);
        $this->assertSame($before, $this->journalSnapshot());
        $this->assertSame(0, Artisan::call('media:responsive-backfill-reconcile', ['--item' => (string) $itemId]));
        $this->assertSame($before, $this->journalSnapshot());
    }

    public static function terminalNoWriteReceiptCases(): array
    {
        return [
            'rejected collision' => [CreateState::Rejected, ObjectAttribution::RejectedCollision, ObjectClassification::Collision],
            'known failed without write' => [CreateState::Failed, ObjectAttribution::FailedWithoutWrite, ObjectClassification::KnownFailedWithoutWrite],
        ];
    }

    #[DataProvider('nonExactObservationCases')]
    public function test_missing_different_or_unreadable_object_prevents_exact_functional_set(string $case): void
    {
        [$journal, , $itemId, $objects, $bytes] = $this->candidatePlan();
        foreach ($objects as $objectId) {
            $journal->commitIntent($objectId);
            $journal->recordReceipt($objectId, new CreateReceipt(CreateState::Unknown));
        }
        $variantId = $objects['variant'];
        foreach ($bytes as $objectId => $expected) {
            if ($case === 'missing' && $objectId === $variantId) {
                continue;
            }
            $stored = $case === 'different' && $objectId === $variantId
                ? str_repeat('x', strlen($expected['bytes']))
                : $expected['bytes'];
            Storage::disk('media_local')->put($expected['key'], $stored);
        }
        if ($case === 'unreadable') {
            config()->set('filesystems.disks.media_local.root', $this->safetyRoot.'/different-root');
        }
        $before = $this->journalSnapshot();

        $report = app(ReconciliationInspector::class)->inspectItem($itemId);

        $this->assertNotSame(ItemClassification::StorageSetExactDomainRevalidationPending, $report->classification);
        $this->assertSame($before, $this->journalSnapshot());
    }

    public static function nonExactObservationCases(): array
    {
        return [
            'missing' => ['missing'],
            'different' => ['different'],
            'unreadable' => ['unreadable'],
        ];
    }

    public function test_different_manifest_and_cleanup_evidence_are_reported_without_changing_history(): void
    {
        [$journal, $runId, $itemId, $objects, $bytes] = $this->candidatePlan();
        $variantId = $objects['variant'];
        $journal->commitIntent($variantId);
        $journal->recordReceipt($variantId, new CreateReceipt(CreateState::Created));
        $journal->updateCleanup($variantId, CleanupState::Pending);
        Storage::disk('media_local')->put($bytes[$variantId]['key'], $bytes[$variantId]['bytes']);
        $manifestId = $objects['manifest'];
        $journal->commitIntent($manifestId);
        $journal->recordReceipt($manifestId, new CreateReceipt(CreateState::Unknown));
        Storage::disk('media_local')->put($bytes[$manifestId]['key'], str_repeat('x', strlen($bytes[$manifestId]['bytes'])));
        $journal->finishItem($itemId, ApplyResult::PublicationUnknown);
        $journal->finishRun($runId, RunState::Failed);
        $before = $this->journalSnapshot();

        $report = app(ReconciliationInspector::class)->inspectItem($itemId);

        $this->assertSame(ItemClassification::CleanupAttentionCandidate, $report->classification);
        $this->assertSame(ObjectClassification::CleanupPendingPresent, $this->objectReport($report, $variantId)->classification);
        $this->assertSame(ObjectClassification::AmbiguousDifferentPresent, $report->manifest->classification);
        $this->assertSame($before, $this->journalSnapshot());
    }

    public function test_storage_identity_drift_makes_exact_key_evidence_unreadable_without_disclosing_configuration(): void
    {
        [$journal, , $itemId, $objects] = $this->candidatePlan();
        $journal->commitIntent($objects['variant']);
        config()->set('filesystems.disks.media_local.root', $this->safetyRoot.'/different-root');

        $exitCode = Artisan::call('media:responsive-backfill-reconcile', ['--object' => (string) $objects['variant']]);
        $output = Artisan::output();

        $this->assertSame(7, $exitCode);
        $this->assertStringContainsString('observation=unreadable', $output);
        $this->assertStringNotContainsString($this->safetyRoot, $output);
        $this->assertSame('intent', $journal->object($objects['variant'])->write_state);
        $this->assertSame('writing', $journal->item($itemId)->phase);
    }

    public function test_run_flags_cover_active_terminal_unfinished_ambiguous_cleanup_and_mixed_evidence(): void
    {
        $journal = app(ApplyJournal::class);
        $active = $journal->createApplyRun($this->identity(), $this->applySelection());
        $activeReport = app(ReconciliationInspector::class)->inspectRun($active, 100);
        $this->assertSame([RunFlag::ActiveNoOtherBlocker], $activeReport->flags);
        $journal->finishRun($active, RunState::Failed);

        [$journal, $mixed, $itemId, $objects] = $this->candidatePlan();
        $journal->commitIntent($objects['variant']);
        $journal->recordReceipt($objects['variant'], new CreateReceipt(CreateState::Created));
        $journal->updateCleanup($objects['variant'], CleanupState::Pending);
        $journal->updateCleanup($objects['variant'], CleanupState::Unknown);
        $journal->commitIntent($objects['manifest']);
        $journal->recordReceipt($objects['manifest'], new CreateReceipt(CreateState::Unknown));
        $journal->finishRun($mixed, RunState::Interrupted);

        $report = app(ReconciliationInspector::class)->inspectRun($mixed, 100);

        $this->assertContains(RunFlag::HasUnfinishedItems, $report->flags);
        $this->assertContains(RunFlag::HasAmbiguousWrites, $report->flags);
        $this->assertContains(RunFlag::HasCleanupAttention, $report->flags);
        $this->assertSame(1, $report->unfinishedItems);
        $this->assertSame(2, $report->unresolvedObjects);
        $this->assertSame('writing', $journal->item($itemId)->phase);
    }

    public function test_item_with_valid_but_wrong_run_domain_is_inconsistent_and_read_only(): void
    {
        $journal = app(ApplyJournal::class);
        $runId = $journal->createApplyRun($this->identity(), $this->applySelection());
        $itemId = $journal->snapshot($runId, $this->excludedItem(123));
        DB::table('media_backfill_items')->where('id', $itemId)->update(['domain' => ManagedMediaDomain::Season->value]);
        $before = $this->journalSnapshot();

        $report = app(ReconciliationInspector::class)->inspectItem($itemId);

        $this->assertSame(ItemClassification::InternallyInconsistent, $report->classification);
        $this->assertSame(7, Artisan::call('media:responsive-backfill-reconcile', ['--item' => (string) $itemId]));
        $this->assertSame($before, $this->journalSnapshot());
    }

    public function test_item_outside_run_selection_range_is_inconsistent_and_read_only(): void
    {
        $journal = app(ApplyJournal::class);
        $runId = $journal->createApplyRun(
            $this->identity(),
            $this->applySelection(ManagedMediaDomain::News, afterId: 100, limit: 10, upperBound: 200),
        );
        $itemId = $journal->snapshot($runId, $this->excludedItem(150));
        DB::table('media_backfill_items')->where('id', $itemId)->update(['entity_id' => 201]);
        $before = $this->journalSnapshot();

        $report = app(ReconciliationInspector::class)->inspectItem($itemId);

        $this->assertSame(ItemClassification::InternallyInconsistent, $report->classification);
        $this->assertSame(7, Artisan::call('media:responsive-backfill-reconcile', ['--item' => (string) $itemId]));
        $this->assertSame($before, $this->journalSnapshot());
    }

    public function test_object_context_fails_closed_on_invalid_item_run_parentage_and_preserves_observation(): void
    {
        [$journal, , $itemId, $objects, $bytes] = $this->candidatePlan();
        $objectId = $objects['variant'];
        $journal->commitIntent($objectId);
        Storage::disk('media_local')->put($bytes[$objectId]['key'], $bytes[$objectId]['bytes']);
        DB::table('media_backfill_items')->where('id', $itemId)->update(['entity_id' => 1001]);
        $beforeJournal = $this->journalSnapshot();
        $beforeStorage = $this->storageSnapshot();

        $context = app(ReconciliationInspector::class)->inspectObjectContext($objectId);

        $this->assertSame(ObjectObservation::ExpectedContentPresent, $context->object->observation);
        $this->assertSame(ObjectAttribution::InconsistentJournal, $context->object->attribution);
        $this->assertSame(ObjectClassification::Inconsistent, $context->object->classification);
        $this->assertSame(7, Artisan::call('media:responsive-backfill-reconcile', ['--object' => (string) $objectId]));
        $output = Artisan::output();
        $this->assertStringContainsString('observation=expected_content_present', $output);
        $this->assertStringContainsString('classification=inconsistent', $output);
        $this->assertSame($beforeJournal, $this->journalSnapshot());
        $this->assertSame($beforeStorage, $this->storageSnapshot());
    }

    public function test_global_unresolved_object_fails_closed_on_invalid_item_run_parentage(): void
    {
        [$journal, $runId, $itemId, $objects, $bytes] = $this->candidatePlan();
        $objectId = $objects['variant'];
        $journal->commitIntent($objectId);
        $journal->recordReceipt($objectId, new CreateReceipt(CreateState::Unknown));
        Storage::disk('media_local')->put($bytes[$objectId]['key'], $bytes[$objectId]['bytes']);
        $journal->finishItem($itemId, ApplyResult::PublicationUnknown);
        $journal->finishRun($runId, RunState::Interrupted);
        DB::table('media_backfill_items')->where('id', $itemId)->update(['entity_id' => 1001]);
        $beforeJournal = $this->journalSnapshot();
        $beforeStorage = $this->storageSnapshot();

        $report = app(ReconciliationInspector::class)->inspectGlobal(0, 100);

        $this->assertSame([], $report->activeRuns);
        $this->assertSame([], $report->unfinishedItems);
        $this->assertCount(1, $report->unresolvedObjects);
        $this->assertSame(ObjectObservation::ExpectedContentPresent, $report->unresolvedObjects[0]->observation);
        $this->assertSame(ObjectClassification::Inconsistent, $report->unresolvedObjects[0]->classification);
        $this->assertSame(7, Artisan::call('media:responsive-backfill-reconcile'));
        $this->assertStringContainsString('classification=inconsistent', Artisan::output());
        $this->assertSame($beforeJournal, $this->journalSnapshot());
        $this->assertSame($beforeStorage, $this->storageSnapshot());
    }

    public function test_object_context_with_valid_parent_keeps_existing_classification(): void
    {
        [$journal, , , $objects, $bytes] = $this->candidatePlan();
        $objectId = $objects['variant'];
        $journal->commitIntent($objectId);
        Storage::disk('media_local')->put($bytes[$objectId]['key'], $bytes[$objectId]['bytes']);
        $beforeJournal = $this->journalSnapshot();
        $beforeStorage = $this->storageSnapshot();

        $context = app(ReconciliationInspector::class)->inspectObjectContext($objectId);

        $this->assertSame(ObjectObservation::ExpectedContentPresent, $context->object->observation);
        $this->assertSame(ObjectAttribution::AttemptAmbiguous, $context->object->attribution);
        $this->assertSame(ObjectClassification::AmbiguousExpectedPresent, $context->object->classification);
        $this->assertSame(0, Artisan::call('media:responsive-backfill-reconcile', ['--object' => (string) $objectId]));
        $this->assertSame($beforeJournal, $this->journalSnapshot());
        $this->assertSame($beforeStorage, $this->storageSnapshot());
    }

    public function test_run_with_more_items_than_selected_limit_is_inconsistent_and_read_only(): void
    {
        $journal = app(ApplyJournal::class);
        $runId = $journal->createApplyRun(
            $this->identity(),
            $this->applySelection(ManagedMediaDomain::News, limit: 1),
        );
        $itemId = $journal->snapshot($runId, $this->excludedItem(100));
        $duplicate = (array) DB::table('media_backfill_items')->where('id', $itemId)->first();
        unset($duplicate['id']);
        $duplicate['entity_id'] = 101;
        DB::table('media_backfill_items')->insert($duplicate);
        $before = $this->journalSnapshot();

        $report = app(ReconciliationInspector::class)->inspectRun($runId, 100);

        $this->assertSame(2, $report->totalItems);
        $this->assertFalse($report->itemsTruncated);
        $this->assertContains(RunFlag::Inconsistent, $report->flags);
        $this->assertSame(7, Artisan::call('media:responsive-backfill-reconcile', ['--run' => $runId, '--limit' => '100']));
        $this->assertSame($before, $this->journalSnapshot());
    }

    public function test_run_limit_bounds_inspected_item_work_and_truncation_is_not_inconsistency(): void
    {
        $journal = app(ApplyJournal::class);
        $runId = $journal->createApplyRun(
            $this->identity(),
            $this->applySelection(ManagedMediaDomain::News, limit: 2),
        );
        [$firstItem] = $this->candidateItem($journal, $runId, 123);
        [$secondItem] = $this->candidateItem($journal, $runId, 124);
        $journal->finishItem($firstItem, ApplyResult::Skipped);
        $journal->finishItem($secondItem, ApplyResult::Skipped);
        $before = $this->journalSnapshot();

        $observer = Mockery::mock(ExactObjectObserver::class);
        $observer->shouldReceive('observe')->times(4)->andReturn(ObjectObservation::AbsentNow);
        $this->app->instance(ExactObjectObserver::class, $observer);

        $report = app(ReconciliationInspector::class)->inspectRun($runId, 1);

        $this->assertCount(1, $report->items);
        $this->assertSame($firstItem, $report->items[0]->id);
        $this->assertTrue($report->itemsTruncated);
        $this->assertSame([RunFlag::DetailsTruncated], $report->flags);
        $this->assertFalse($report->preventsTrustworthyClassification());
        $this->assertSame(0, Artisan::call('media:responsive-backfill-reconcile', ['--run' => $runId, '--limit' => '1']));
        $output = Artisan::output();
        $this->assertStringContainsString('flags=details_truncated', $output);
        $this->assertStringContainsString('Items detallados: 1/2', $output);
        $this->assertStringContainsString('Detalle de items truncado por --limit: sí', $output);
        $this->assertStringNotContainsString('active_no_other_blocker', $output);
        $this->assertStringNotContainsString('inconsistent', $output);
        $this->assertSame($before, $this->journalSnapshot());
    }

    public function test_cli_contract_help_validation_selectors_summary_and_sanitized_failures(): void
    {
        $command = $this->app->make(ResponsiveBackfillReconcileCommand::class);
        foreach (['run', 'item', 'object', 'after-id', 'limit'] as $option) {
            $this->assertTrue($command->getDefinition()->hasOption($option));
        }
        foreach (['execute', 'cleanup', 'finalize', 'resume', 'apply'] as $option) {
            $this->assertFalse($command->getDefinition()->hasOption($option));
        }

        [$helpExit, $help] = $this->callRaw('--help');
        $this->assertSame(0, $helpExit);
        $this->assertStringContainsString('exclusivamente read-only', strtolower($command->getDescription()));
        $this->assertStringNotContainsString('--resume', $help);

        foreach ([
            '--run=INVALID',
            '--run=550E8400-E29B-41D4-A716-446655440000',
            '--item=0',
            '--object=-1',
            '--limit=0',
            '--limit=1001',
            '--after-id=-1',
            '--item=1 --object=1',
            '--item=1 --limit=1',
            '--object=1 --limit=1',
            '--run=550e8400-e29b-41d4-a716-446655440000 --after-id=1',
            '--resume',
            '--execute',
            '--cleanup',
            '--finalize',
        ] as $arguments) {
            [$exitCode, $output] = $this->callRaw($arguments);
            $this->assertSame(2, $exitCode, $arguments.' '.$output);
            $this->assertStringContainsString('read-only', $output);
        }

        [$journal, $runId, $itemId, $objects] = $this->candidatePlan();
        $journal->commitIntent($objects['variant']);
        $before = $this->journalSnapshot();
        $this->assertSame(0, Artisan::call('media:responsive-backfill-reconcile', ['--limit' => '1']));
        $summary = Artisan::output();
        $this->assertStringContainsString('Recovery barrier global (observación actual): blocked', $summary);
        $this->assertStringContainsString('Ejecuciones activas mostradas: 1', $summary);
        $this->assertStringContainsString('Items sin terminar mostrados: 1', $summary);
        $this->assertStringContainsString('Objetos no resueltos mostrados: 1', $summary);
        $this->assertStringContainsString('Continuación objetos: --after-id='.$objects['variant'], $summary);
        $this->assertSame(0, Artisan::call('media:responsive-backfill-reconcile', ['--run' => $runId, '--limit' => '1']));
        $this->assertStringContainsString('Run '.$runId, Artisan::output());
        $this->assertSame(0, Artisan::call('media:responsive-backfill-reconcile', ['--item' => (string) $itemId]));
        $this->assertStringContainsString('Item '.$itemId, Artisan::output());
        $this->assertSame(0, Artisan::call('media:responsive-backfill-reconcile', ['--object' => (string) $objects['variant']]));
        $this->assertStringContainsString('Context: run='.$runId, Artisan::output());
        $this->assertSame($before, $this->journalSnapshot());
    }

    public function test_read_only_components_have_no_mutation_or_listing_dependencies(): void
    {
        $commandSource = file_get_contents(app_path('Console/Commands/ResponsiveBackfillReconcileCommand.php'));
        $inspectorSource = file_get_contents(app_path('Services/Media/Backfill/Reconciliation/ReconciliationInspector.php'));
        $observerSource = file_get_contents(app_path('Services/Media/Backfill/Reconciliation/ExactObjectObserver.php'));

        foreach ([
            ApplyItemPublisher::class,
            JournaledObjectWriter::class,
            ExclusiveObjectCreator::class,
            MariaDbBackfillLock::class,
            ApplyMaintenanceGuard::class,
        ] as $forbidden) {
            $short = class_basename($forbidden);
            $this->assertStringNotContainsString($short, $commandSource);
            $this->assertStringNotContainsString($short, $inspectorSource);
            $this->assertStringNotContainsString($short, $observerSource);
        }
        foreach (['finishItem', 'finishRun', 'recordReceipt', 'updateCleanup', 'advanceCheckpoint', 'commitIntent'] as $mutation) {
            $this->assertStringNotContainsString($mutation.'(', $commandSource.$inspectorSource.$observerSource);
        }
        foreach (['listContents', 'allFiles(', 'files(', 'directories('] as $listing) {
            $this->assertStringNotContainsString($listing, $commandSource.$inspectorSource.$observerSource);
        }
        $this->assertStringNotContainsString('delete(', $commandSource.$inspectorSource.$observerSource);
        $this->assertStringNotContainsString('put(', $commandSource.$inspectorSource.$observerSource);
    }

    /** @return array{ApplyJournal, string, int, array{variant: int, manifest: int}, array<int, array{key: string, bytes: string}>} */
    private function candidatePlan(): array
    {
        $journal = app(ApplyJournal::class);
        $runId = $journal->createApplyRun($this->identity(), $this->applySelection());
        [$itemId, $objects, $bytes] = $this->candidateItem($journal, $runId, 123);

        return [$journal, $runId, $itemId, $objects, $bytes];
    }

    /** @return array{int, array{variant: int, manifest: int}, array<int, array{key: string, bytes: string}>} */
    private function candidateItem(ApplyJournal $journal, string $runId, int $entityId): array
    {
        $preflight = $this->preflight($entityId);
        $itemId = $journal->snapshot($runId, $preflight);
        $objects = [];
        $bytes = [];
        foreach ($preflight->prepared->manifest->variants as $index => $descriptor) {
            $image = $preflight->prepared->variants[$index];
            $target = new TargetObject(
                $descriptor->key,
                ObjectKind::Variant,
                hash('sha256', $image->bytes),
                $image->size,
                $image->mimeType,
            );
            $objectId = $journal->planObject($itemId, $target);
            $objects['variant'] = $objectId;
            $bytes[$objectId] = ['key' => $target->key, 'bytes' => $image->bytes];
        }
        $manifestBytes = $preflight->prepared->manifest->toJson();
        $manifestTarget = new TargetObject(
            $journal->item($itemId)->manifest_key,
            ObjectKind::Manifest,
            hash('sha256', $manifestBytes),
            strlen($manifestBytes),
            'application/json',
        );
        $manifestId = $journal->planObject($itemId, $manifestTarget);
        $objects['manifest'] = $manifestId;
        $bytes[$manifestId] = ['key' => $manifestTarget->key, 'bytes' => $manifestBytes];
        $journal->markRevalidated($itemId);

        return [$itemId, $objects, $bytes];
    }

    private function excludedItem(int $entityId): PreflightResult
    {
        return new PreflightResult(
            new ManagedMediaReference(ManagedMediaDomain::News, $entityId, null),
            PreflightClassification::ExcludedNull,
        );
    }

    private function objectReport(ItemReconciliationReport $itemReport, int $objectId): ObjectReconciliationReport
    {
        foreach ($itemReport->objects as $object) {
            if ($object->id === $objectId) {
                return $object;
            }
        }
        $this->fail('Missing object report.');
    }

    private function journalSnapshot(): array
    {
        return [
            'runs' => DB::table('media_backfill_runs')->orderBy('run_id')->get()->map(fn ($row) => (array) $row)->all(),
            'items' => DB::table('media_backfill_items')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'objects' => DB::table('media_backfill_objects')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
        ];
    }

    private function storageSnapshot(): array
    {
        $snapshot = [];
        foreach (Storage::disk('media_local')->allFiles() as $key) {
            $snapshot[$key] = hash('sha256', Storage::disk('media_local')->get($key));
        }
        ksort($snapshot);

        return $snapshot;
    }

    /** @return array{int, string} */
    private function callRaw(string $arguments): array
    {
        $input = new StringInput('media:responsive-backfill-reconcile'.($arguments === '' ? '' : ' '.$arguments));
        $output = new BufferedOutput;
        $exitCode = $this->app->make(ConsoleKernel::class)->handle($input, $output);

        return [$exitCode, $output->fetch()];
    }
}
