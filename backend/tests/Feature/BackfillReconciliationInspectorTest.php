<?php

namespace Tests\Feature;

use App\Console\Commands\ResponsiveBackfillReconcileCommand;
use App\Services\Media\Backfill\ApplyItemPublisher;
use App\Services\Media\Backfill\ManagedMediaDomain;
use App\Services\Media\Backfill\Reconciliation\ExactObjectObserver;
use App\Services\Media\Backfill\Reconciliation\ItemClassification;
use App\Services\Media\Backfill\Reconciliation\ItemReconciliationReport;
use App\Services\Media\Backfill\Reconciliation\ItemReconciliationResult;
use App\Services\Media\Backfill\Reconciliation\ObjectAttribution;
use App\Services\Media\Backfill\Reconciliation\ObjectClassification;
use App\Services\Media\Backfill\Reconciliation\ObjectObservation;
use App\Services\Media\Backfill\Reconciliation\ObjectReconciliationReport;
use App\Services\Media\Backfill\Reconciliation\ObjectReconciliationResolution;
use App\Services\Media\Backfill\Reconciliation\ReconciliationBlockReason;
use App\Services\Media\Backfill\Reconciliation\ReconciliationEventType;
use App\Services\Media\Backfill\Reconciliation\ReconciliationEventValidator;
use App\Services\Media\Backfill\Reconciliation\ReconciliationInspector;
use App\Services\Media\Backfill\Reconciliation\ReconciliationJournal;
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
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Concerns\BackfillSafetyFixtures;
use Tests\TestCase;

class BackfillReconciliationInspectorTest extends TestCase
{
    use BackfillSafetyFixtures;
    use DatabaseTruncation;

    private const EVENTS = 'media_backfill_reconciliation_events';

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
            $this->releaseBackfillLock();
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
            if (preg_match('/media_backfill_(runs|items|objects|reconciliation_events)/i', $query->sql) === 1) {
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
        $this->assertTrue($report->functionalStorageSetExact);
        $this->assertNull($report->reconciliationResult);
        $this->assertFalse($report->reconciliationResultInvalid);
        $this->assertFalse($report->hasReconciliationEvent);
        $this->assertStringContainsString('storage_set_exact_domain_revalidation_pending_d2_c', $output);
        $this->assertStringContainsString('functional_storage_set_exact=yes', $output);
        $this->assertStringContainsString('durable_reconciliation=unresolved', $output);
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
        $this->assertNull($report->reconciliationResolution);
        $this->assertFalse($report->reconciliationResolutionInvalid);
        $this->assertFalse($report->hasReconciliationEvent);
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
        $this->assertFalse($report->functionalStorageSetExact);
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
        $this->assertFalse($report->functionalStorageSetExact);
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
        $this->assertTrue($report->functionalStorageSetExact);
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
        $this->assertFalse($report->functionalStorageSetExact);
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

    #[DataProvider('cleanupStatesCompatibleWithFunctionalExactness')]
    public function test_functional_exactness_is_independent_of_cleanup_attention(CleanupState $cleanup): void
    {
        [$journal, , $itemId, $objects, $bytes] = $this->candidatePlan();
        foreach ($objects as $objectId) {
            $journal->commitIntent($objectId);
            $journal->recordReceipt($objectId, new CreateReceipt(CreateState::Created));
            Storage::disk('media_local')->put($bytes[$objectId]['key'], $bytes[$objectId]['bytes']);
        }
        if ($cleanup !== CleanupState::NotRequired) {
            $journal->updateCleanup($objects['variant'], CleanupState::Pending);
            if ($cleanup !== CleanupState::Pending) {
                $journal->updateCleanup($objects['variant'], $cleanup);
            }
        }

        $report = app(ReconciliationInspector::class)->inspectItem($itemId);

        $this->assertTrue($report->functionalStorageSetExact);
        $this->assertSame(
            $cleanup === CleanupState::NotRequired
                ? ItemClassification::StorageSetExactDomainRevalidationPending
                : ItemClassification::CleanupAttentionCandidate,
            $report->classification,
        );
        $this->assertSame(RecoveryBarrierState::Blocked, $journal->recoveryBarrier());
    }

    public static function cleanupStatesCompatibleWithFunctionalExactness(): array
    {
        return [
            'not required' => [CleanupState::NotRequired],
            'pending' => [CleanupState::Pending],
            'failed' => [CleanupState::Failed],
            'unknown' => [CleanupState::Unknown],
        ];
    }

    public function test_deleted_cleanup_history_prevents_functional_exactness_even_when_bytes_are_exact(): void
    {
        [$journal, , $itemId, $objects, $bytes] = $this->candidatePlan();
        foreach ($objects as $objectId) {
            $journal->commitIntent($objectId);
            $journal->recordReceipt($objectId, new CreateReceipt(CreateState::Created));
            Storage::disk('media_local')->put($bytes[$objectId]['key'], $bytes[$objectId]['bytes']);
        }
        $journal->updateCleanup($objects['variant'], CleanupState::Pending);
        $journal->updateCleanup($objects['variant'], CleanupState::Deleted);

        $this->assertFalse(app(ReconciliationInspector::class)->inspectItem($itemId)->functionalStorageSetExact);
    }

    #[DataProvider('invalidPlannedSetCases')]
    public function test_missing_or_duplicate_planned_rows_prevent_functional_exactness(string $case): void
    {
        [$journal, , $itemId, $objects, $bytes] = $this->candidatePlan();
        foreach ($bytes as $expected) {
            Storage::disk('media_local')->put($expected['key'], $expected['bytes']);
        }
        if ($case === 'missing_manifest') {
            DB::table('media_backfill_objects')->where('id', $objects['manifest'])->delete();
        } elseif ($case === 'missing_variant') {
            DB::table('media_backfill_objects')->where('id', $objects['variant'])->delete();
        } else {
            $duplicate = (array) DB::table('media_backfill_objects')->where('id', $objects[$case])->first();
            unset($duplicate['id']);
            $duplicate['object_key'] .= '.duplicate';
            DB::table('media_backfill_objects')->insert($duplicate);
        }

        $this->assertFalse(app(ReconciliationInspector::class)->inspectItem($itemId)->functionalStorageSetExact);
    }

    public static function invalidPlannedSetCases(): array
    {
        return [
            'missing manifest' => ['missing_manifest'],
            'missing variant' => ['missing_variant'],
            'duplicate manifest' => ['manifest'],
            'duplicate variant' => ['variant'],
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
        $this->assertFalse($report->functionalStorageSetExact);
        $this->assertSame(ObjectClassification::CleanupPendingPresent, $this->objectReport($report, $variantId)->classification);
        $this->assertSame(ObjectClassification::AmbiguousDifferentPresent, $report->manifest->classification);
        $this->assertSame($before, $this->journalSnapshot());
    }

    public function test_storage_identity_drift_makes_exact_key_evidence_unreadable_without_disclosing_configuration(): void
    {
        [$journal, , $itemId, $objects, $bytes] = $this->candidatePlan();
        $journal->commitIntent($objects['variant']);
        foreach ($bytes as $expected) {
            Storage::disk('media_local')->put($expected['key'], $expected['bytes']);
        }
        config()->set('filesystems.disks.media_local.root', $this->safetyRoot.'/different-root');

        $exitCode = Artisan::call('media:responsive-backfill-reconcile', ['--object' => (string) $objects['variant']]);
        $output = Artisan::output();

        $this->assertSame(7, $exitCode);
        $this->assertStringContainsString('observation=unreadable', $output);
        $this->assertStringNotContainsString($this->safetyRoot, $output);
        $this->assertSame('intent', $journal->object($objects['variant'])->write_state);
        $this->assertSame('writing', $journal->item($itemId)->phase);
        $this->assertFalse(app(ReconciliationInspector::class)->inspectItem($itemId)->functionalStorageSetExact);
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
        $this->assertFalse($report->functionalStorageSetExact);
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
        $this->assertFalse($report->functionalStorageSetExact);
        $this->assertSame(7, Artisan::call('media:responsive-backfill-reconcile', ['--item' => (string) $itemId]));
        $this->assertSame($before, $this->journalSnapshot());
    }

    public function test_object_context_fails_closed_on_invalid_item_run_parentage_and_preserves_observation(): void
    {
        [$journal, , $itemId, $objects, $bytes] = $this->candidatePlan();
        $objectId = $objects['variant'];
        $journal->commitIntent($objectId);
        foreach ($bytes as $expected) {
            Storage::disk('media_local')->put($expected['key'], $expected['bytes']);
        }
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
        $this->assertFalse(app(ReconciliationInspector::class)->inspectItem($itemId)->functionalStorageSetExact);
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

    public function test_valid_durable_projections_are_reported_but_do_not_override_the_recovery_barrier(): void
    {
        [$journal, $runId, $itemId, $objects, $bytes] = $this->candidatePlan();
        $journal->commitIntent($objects['variant']);
        foreach ($bytes as $expected) {
            Storage::disk('media_local')->put($expected['key'], $expected['bytes']);
        }
        $event = $this->acceptForward($runId, $itemId, 90, 1, 2);
        $before = $this->journalSnapshot();

        $run = app(ReconciliationInspector::class)->inspectRun($runId, 100);
        $item = app(ReconciliationInspector::class)->inspectItem($itemId);
        $object = app(ReconciliationInspector::class)->inspectObject($objects['variant']);
        $this->assertFalse($run->hasReconciliationEvent);
        $this->assertFalse($run->reconciliationEventPointerInvalid);
        $this->assertSame(ItemReconciliationResult::ForwardAccepted, $item->reconciliationResult);
        $this->assertFalse($item->reconciliationResultInvalid);
        $this->assertFalse($item->preventsTrustworthyClassification());
        $this->assertSame(ObjectReconciliationResolution::ForwardRetained, $object->reconciliationResolution);
        $this->assertFalse($object->reconciliationResolutionInvalid);
        $this->assertSame(RecoveryBarrierState::Blocked, $journal->recoveryBarrier());

        $this->assertSame(0, Artisan::call('media:responsive-backfill-reconcile', ['--item' => (string) $itemId]));
        $output = Artisan::output();
        $this->assertStringContainsString('durable_reconciliation=forward_accepted', $output);
        $this->assertStringContainsString('reconciliation_event=sha256:', $output);
        $this->assertStringNotContainsString($event, $output);
        $this->assertSame($before, $this->journalSnapshot());
        $this->assertSame('active', $journal->run($runId)->state);
        $this->assertSame('writing', $journal->item($itemId)->phase);
        $this->assertSame('intent', $journal->object($objects['variant'])->write_state);
    }

    #[DataProvider('brokenProjectionLinks')]
    public function test_semantically_broken_projection_links_are_reported_as_inconsistent(string $case): void
    {
        [$journal, $runId, $itemId, $objects, $bytes] = $this->candidatePlan();
        foreach ($bytes as $expected) {
            Storage::disk('media_local')->put($expected['key'], $expected['bytes']);
        }
        $this->acceptForward($runId, $itemId, 90, 1, 2);
        $this->breakProjectionLink($case, $journal, $runId, $itemId, $objects['variant']);
        $before = $this->journalSnapshot();

        $item = app(ReconciliationInspector::class)->inspectItem($itemId);
        $object = app(ReconciliationInspector::class)->inspectObject($objects['variant']);

        $this->assertTrue($item->preventsTrustworthyClassification(), $case);
        $this->assertSame(ItemClassification::InternallyInconsistent, $item->classification, $case);
        $this->assertFalse($item->functionalStorageSetExact, $case);
        // A partial projection is only visible as a cross-projection contradiction: every other
        // corruption also invalidates the individual durable link it names.
        $linkFlagged = $item->reconciliationResultInvalid || $object->reconciliationResolutionInvalid
            || $this->objectReport($item, $objects['variant'])->reconciliationResolutionInvalid;
        $this->assertSame($case !== 'partial projection', $linkFlagged, $case);
        $this->assertSame(7, Artisan::call('media:responsive-backfill-reconcile', ['--item' => (string) $itemId]), $case);
        $this->assertSame(RecoveryBarrierState::Blocked, $journal->recoveryBarrier());
        $this->assertSame($before, $this->journalSnapshot(), $case);
    }

    public static function brokenProjectionLinks(): array
    {
        return [
            'pointer to a missing event' => ['missing event'],
            'pointer to another event type' => ['wrong type'],
            'pointer to an event of another run' => ['foreign run'],
            'pointer to an event of another item' => ['foreign item'],
            'event with a broken evidence hash' => ['bad evidence hash'],
            'event with a malformed evidence envelope' => ['malformed envelope'],
            'item and object naming different events' => ['crossed pointers'],
            'partial object projection' => ['partial projection'],
            'resolution whose attempt start is missing' => ['missing start'],
            'resolution whose attempt start is corrupt' => ['corrupt start'],
        ];
    }

    public function test_a_run_pointer_to_a_non_closure_event_is_invalid(): void
    {
        [$journal, $runId, $itemId] = $this->candidatePlan();
        $this->acceptForward($runId, $itemId, 90, 1, 2);
        Schema::withoutForeignKeyConstraints(fn () => DB::table('media_backfill_runs')->where('run_id', $runId)
            ->update(['reconciliation_event_id' => $this->reconciliationUuid(1)]));
        $before = $this->journalSnapshot();

        $run = app(ReconciliationInspector::class)->inspectRun($runId, 100);

        $this->assertTrue($run->hasReconciliationEvent);
        $this->assertTrue($run->reconciliationEventPointerInvalid);
        $this->assertContains(RunFlag::Inconsistent, $run->flags);
        $this->assertSame(7, Artisan::call('media:responsive-backfill-reconcile', ['--run' => $runId]));
        $this->assertSame($before, $this->journalSnapshot());
        $this->assertSame(RecoveryBarrierState::Blocked, $journal->recoveryBarrier());
    }

    private function breakProjectionLink(
        string $case,
        ApplyJournal $journal,
        string $runId,
        int $itemId,
        int $objectId,
    ): void {
        $events = fn () => DB::table('media_backfill_reconciliation_events');
        $forward = $this->reconciliationUuid(2);
        $start = $this->reconciliationUuid(1);
        $pointer = match ($case) {
            'missing event' => $this->reconciliationUuid(999),
            'wrong type' => $this->blockedEvent($runId, $itemId),
            'foreign run' => $this->foreignForwardEvent(true),
            'foreign item' => $this->foreignForwardEvent(false, $runId, $journal),
            default => null,
        };
        if ($pointer !== null) {
            Schema::withoutForeignKeyConstraints(fn () => DB::table('media_backfill_items')->where('id', $itemId)
                ->update(['reconciliation_event_id' => $pointer]));

            return;
        }
        match ($case) {
            'bad evidence hash' => $events()->where('event_id', $forward)
                ->update(['evidence_sha256' => str_repeat('0', 64)]),
            'malformed envelope' => $events()->where('event_id', $forward)
                ->update(['evidence_json' => '{}', 'evidence_sha256' => hash('sha256', '{}')]),
            'crossed pointers' => Schema::withoutForeignKeyConstraints(
                fn () => DB::table('media_backfill_objects')->where('id', $objectId)
                    ->update(['reconciliation_event_id' => $this->foreignForwardEvent(false, $runId, $journal)])),
            'partial projection' => DB::table('media_backfill_objects')->where('id', $objectId)
                ->update(['reconciliation_resolution' => null, 'reconciliation_event_id' => null]),
            'missing start' => $events()->where('event_id', $start)->delete(),
            'corrupt start' => $events()->where('event_id', $start)
                ->update(['evidence_sha256' => str_repeat('0', 64)]),
        };
    }

    public function test_cross_projection_identity_requires_the_exact_event_not_a_display_fingerprint(): void
    {
        [$journal, $runId, $itemId, $objects, $bytes] = $this->candidatePlan();
        foreach ($bytes as $expected) {
            Storage::disk('media_local')->put($expected['key'], $expected['bytes']);
        }
        $first = $this->acceptForward($runId, $itemId, 90, 1, 2);
        // Clearing the projections makes a second, independently valid acceptance of the same item
        // reachable, so both events remain valid links for this exact run and item.
        DB::table('media_backfill_items')->where('id', $itemId)
            ->update(['reconciliation_result' => null, 'reconciliation_event_id' => null]);
        DB::table('media_backfill_objects')->where('item_id', $itemId)
            ->update(['reconciliation_resolution' => null, 'reconciliation_event_id' => null]);
        $second = $this->acceptForward($runId, $itemId, 92, 6, 7);
        $this->assertNotSame($first, $second);
        DB::table('media_backfill_objects')->where('id', $objects['variant'])
            ->update(['reconciliation_event_id' => $first]);
        $before = $this->journalSnapshot();

        $item = app(ReconciliationInspector::class)->inspectItem($itemId);
        $object = $this->objectReport($item, $objects['variant']);

        $this->assertSame(ItemReconciliationResult::ForwardAccepted, $item->reconciliationResult);
        $this->assertFalse($item->reconciliationResultInvalid);
        $this->assertSame(ObjectReconciliationResolution::ForwardRetained, $object->reconciliationResolution);
        $this->assertFalse($object->reconciliationResolutionInvalid);
        $this->assertSame(ItemClassification::InternallyInconsistent, $item->classification);
        $this->assertTrue($item->preventsTrustworthyClassification());
        $this->assertFalse($item->functionalStorageSetExact);
        $this->assertSame(7, Artisan::call('media:responsive-backfill-reconcile', ['--item' => (string) $itemId]));
        $this->assertSame($before, $this->journalSnapshot());
        $this->assertSame(RecoveryBarrierState::Blocked, $journal->recoveryBarrier());

        $method = new ReflectionMethod(ReconciliationInspector::class, 'crossProjectionInvalid');
        $source = implode('', array_slice(file($method->getFileName()), $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1));
        $this->assertStringContainsString('reconciliation_event_id', $source);
        $this->assertStringNotContainsString('Fingerprint', $source);
    }

    public function test_event_provenance_must_match_the_durable_run_storage_identity(): void
    {
        [$journal, $runId, $itemId, $objects, $bytes] = $this->candidatePlan();
        foreach ($bytes as $expected) {
            Storage::disk('media_local')->put($expected['key'], $expected['bytes']);
        }
        $this->acceptForward($runId, $itemId, 90, 1, 2);
        $accepted = app(ReconciliationInspector::class)->inspectItem($itemId);
        $this->assertSame(ItemReconciliationResult::ForwardAccepted, $accepted->reconciliationResult);
        $this->assertFalse($accepted->reconciliationResultInvalid);

        // Start and resolution keep agreeing with each other, and each stays individually valid,
        // but they no longer agree with the storage identity the run was journaled with.
        $foreign = str_repeat('b', 64);
        $validator = app(ReconciliationEventValidator::class);
        foreach ([1, 2] as $number) {
            $this->reidentifyEvent($this->reconciliationUuid($number), $foreign);
            $this->assertTrue($validator->isStructurallyValid(DB::table(self::EVENTS)
                ->where('event_id', $this->reconciliationUuid($number))->first()));
        }
        $this->assertSame($this->identity()->hash, DB::table('media_backfill_runs')
            ->where('run_id', $runId)->value('storage_identity_hash'));
        $before = $this->journalSnapshot();

        $item = app(ReconciliationInspector::class)->inspectItem($itemId);

        $this->assertTrue($item->reconciliationResultInvalid);
        $this->assertTrue($this->objectReport($item, $objects['variant'])->reconciliationResolutionInvalid);
        $this->assertSame(ItemClassification::InternallyInconsistent, $item->classification);
        $this->assertFalse($item->functionalStorageSetExact);
        $this->assertSame(7, Artisan::call('media:responsive-backfill-reconcile', ['--item' => (string) $itemId]));
        $this->assertSame($before, $this->journalSnapshot());
        $this->assertSame(RecoveryBarrierState::Blocked, $journal->recoveryBarrier());
    }

    /** Rewrites one event's storage identity coherently: scalar column, envelope and evidence hash. */
    private function reidentifyEvent(string $eventId, string $hash): void
    {
        $event = DB::table(self::EVENTS)->where('event_id', $eventId)->first();
        $evidence = json_decode($event->evidence_json, true, 8, JSON_THROW_ON_ERROR);
        $evidence['storage_identity_hash'] = $hash;
        $json = json_encode($evidence, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        DB::table(self::EVENTS)->where('event_id', $eventId)->update([
            'storage_identity_hash' => $hash,
            'evidence_json' => $json,
            'evidence_sha256' => hash('sha256', $json),
        ]);
    }

    public function test_a_closed_no_effect_item_may_not_carry_object_projections(): void
    {
        [$journal, $runId, $itemId, $objects] = $this->candidatePlan();
        $context = $this->reconciliationContext(90);
        app(ReconciliationJournal::class)->beginRunReconciliation($runId, $this->reconciliationUuid(1),
            $this->reconciliationMoment(), $context);
        app(ReconciliationJournal::class)->recordNoEffectItemResolution($runId, $itemId,
            $this->reconciliationUuid(2), $this->reconciliationMoment(), $context);
        $clean = app(ReconciliationInspector::class)->inspectItem($itemId);
        $this->assertSame(ItemReconciliationResult::ClosedNoEffect, $clean->reconciliationResult);
        $this->assertFalse($clean->reconciliationResultInvalid);

        DB::table('media_backfill_objects')->where('id', $objects['variant'])->update([
            'reconciliation_resolution' => ObjectReconciliationResolution::ForwardRetained->value,
            'reconciliation_event_id' => $this->foreignForwardEvent(false, $runId, $journal),
        ]);
        $before = $this->journalSnapshot();

        $item = app(ReconciliationInspector::class)->inspectItem($itemId);

        $this->assertSame(ItemClassification::InternallyInconsistent, $item->classification);
        $this->assertTrue($item->preventsTrustworthyClassification());
        $this->assertSame(7, Artisan::call('media:responsive-backfill-reconcile', ['--item' => (string) $itemId]));
        $this->assertSame($before, $this->journalSnapshot());
        $this->assertSame(RecoveryBarrierState::Blocked, $journal->recoveryBarrier());
    }

    /** A valid blocked event of the same attempt, used as an incompatible pointer target. */
    private function blockedEvent(string $runId, int $itemId): string
    {
        app(ReconciliationJournal::class)->recordBlockedAttempt($runId, $itemId, $this->reconciliationUuid(3),
            ReconciliationBlockReason::EvidenceMismatch, $this->reconciliationMoment(),
            $this->reconciliationContext(90));

        return $this->reconciliationUuid(3);
    }

    /** A second, otherwise valid forward acceptance used as a foreign pointer target. */
    private function foreignForwardEvent(bool $otherRun, ?string $runId = null, ?ApplyJournal $journal = null): string
    {
        $journal ??= app(ApplyJournal::class);
        $eventRun = $journal->createApplyRun($this->identity(), $this->applySelection());
        [$itemId] = $this->candidateItem($journal, $eventRun, 200);
        $event = $this->acceptForward($eventRun, $itemId, 91, 4, 5);
        if (! $otherRun && $runId !== null) {
            DB::table(self::EVENTS)->whereIn('event_id', [
                $this->reconciliationUuid(4),
                $this->reconciliationUuid(5),
            ])->update(['run_id' => $runId]);
        }

        return $event;
    }

    public function test_unknown_durable_projection_is_invalid_unresolved_and_read_only(): void
    {
        [$journal, $runId, $itemId, $objects] = $this->candidatePlan();
        $journal->commitIntent($objects['variant']);
        $event = $this->injectInvalidReconciliationEvent($runId, $itemId, ReconciliationEventType::AttemptBlocked);
        DB::table('media_backfill_objects')->where('id', $objects['variant'])->update([
            'reconciliation_resolution' => 'future_unknown',
            'reconciliation_event_id' => $event,
        ]);
        DB::table('media_backfill_items')->where('id', $itemId)->update([
            'reconciliation_result' => 'future_unknown',
            'reconciliation_event_id' => $event,
        ]);
        $before = $this->journalSnapshot();

        $report = app(ReconciliationInspector::class)->inspectObject($objects['variant']);
        $itemReport = app(ReconciliationInspector::class)->inspectItem($itemId);

        $this->assertNull($report->reconciliationResolution);
        $this->assertTrue($report->reconciliationResolutionInvalid);
        $this->assertTrue($report->preventsTrustworthyClassification());
        $this->assertNull($itemReport->reconciliationResult);
        $this->assertTrue($itemReport->reconciliationResultInvalid);
        $this->assertFalse($itemReport->functionalStorageSetExact);
        $this->assertSame(RecoveryBarrierState::Blocked, $journal->recoveryBarrier());
        $this->assertSame(7, Artisan::call('media:responsive-backfill-reconcile', ['--item' => (string) $itemId]));
        $this->assertStringContainsString('durable_reconciliation=invalid', Artisan::output());
        $this->assertStringNotContainsString($event, Artisan::output());
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
        foreach (['beginRunReconciliation', 'recordBlockedAttempt', 'recordForwardItemResolution',
            'recordNoEffectItemResolution', 'closeReconciledRun', 'recordCleanupResolution'] as $method) {
            $this->assertFalse(method_exists(ApplyJournal::class, $method));
        }
        // D2-B2 owns the mutation repository; read-only inspection must never depend on it.
        $this->assertStringNotContainsString('ReconciliationJournal', $commandSource.$inspectorSource.$observerSource);
        foreach (['listContents', 'allFiles(', 'files(', 'directories('] as $listing) {
            $this->assertStringNotContainsString($listing, $commandSource.$inspectorSource.$observerSource);
        }
        $this->assertStringNotContainsString('delete(', $commandSource.$inspectorSource.$observerSource);
        $this->assertStringNotContainsString('put(', $commandSource.$inspectorSource.$observerSource);
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
            'reconciliation_events' => DB::table('media_backfill_reconciliation_events')->orderBy('event_id')->get()->map(fn ($row) => (array) $row)->all(),
        ];
    }

    private function injectInvalidReconciliationEvent(
        string $runId,
        int $itemId,
        ReconciliationEventType $type,
    ): string {
        $event = '550e8400-e29b-41d4-a716-446655440001';
        DB::table('media_backfill_reconciliation_events')->insert([
            'event_id' => $event,
            'attempt_id' => '550e8400-e29b-41d4-a716-446655440002',
            'run_id' => $runId,
            'item_id' => $itemId,
            'event_type' => $type->value,
            'evidence_version' => 1,
            'storage_identity_hash' => $this->identity()->hash,
            'backend_mode' => 'local',
            'code_revision' => str_repeat('a', 64),
            'evidence_sha256' => hash('sha256', '{}'),
            'evidence_json' => '{}',
            'created_at' => now(),
        ]);

        return $event;
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
