<?php

namespace Tests\Feature;

use App\Models\NewsArticle;
use App\Services\Media\Backfill\InspectionReason;
use App\Services\Media\Backfill\ManagedMediaDomain;
use App\Services\Media\Backfill\ManagedMediaReference;
use App\Services\Media\Backfill\PreflightClassification;
use App\Services\Media\Backfill\PreflightResult;
use App\Services\Media\Backfill\Safety\ApplyJournal;
use App\Services\Media\Backfill\Safety\ApplyMaintenanceGuard;
use App\Services\Media\Backfill\Safety\ApplyResult;
use App\Services\Media\Backfill\Safety\BackfillSafetyException;
use App\Services\Media\Backfill\Safety\CleanupState;
use App\Services\Media\Backfill\Safety\CreateReceipt;
use App\Services\Media\Backfill\Safety\CreateState;
use App\Services\Media\Backfill\Safety\ExclusiveObjectCreator;
use App\Services\Media\Backfill\Safety\JournaledObjectWriter;
use App\Services\Media\Backfill\Safety\MariaDbBackfillLock;
use App\Services\Media\Backfill\Safety\ObjectKind;
use App\Services\Media\Backfill\Safety\RunState;
use App\Services\Media\Backfill\Safety\SafetyError;
use App\Services\Media\Backfill\Safety\TargetObject;
use App\Services\Media\ResponsiveManifest;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\BackfillSafetyFixtures;
use Tests\TestCase;

class BackfillApplyJournalTest extends TestCase
{
    use BackfillSafetyFixtures;
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('galotxas_testing', DB::connection()->getDatabaseName());
        $this->assertSame('test-db', DB::connection()->getConfig('host'));
        $this->assertSame(0, DB::transactionLevel());
        $this->setupSafetyStorage();
    }

    protected function tearDown(): void
    {
        try {
            if (DB::transactionLevel() > 0) {
                DB::rollBack(0);
            }
            $this->truncateTablesForAllConnections();
            $this->cleanupSafetyStorage();
        } finally {
            parent::tearDown();
        }
    }

    public function test_schema_columns_indexes_and_only_restrict_internal_foreign_keys(): void
    {
        $expected = [
            'runs' => ['run_id', 'mode', 'state', 'options_json', 'storage_identity_hash', 'code_revision', 'upper_bounds_json', 'checkpoints_json', 'summary_json', 'started_at', 'heartbeat_at', 'finished_at', 'error_code', 'created_at', 'updated_at'],
            'items' => ['id', 'run_id', 'domain', 'entity_id', 'master_key', 'master_key_hash', 'reference_sample', 'manifest_key', 'preflight_classification', 'reason_codes_json', 'phase', 'apply_result', 'source_sha256', 'candidate_manifest_sha256', 'candidate_manifest_json', 'inspected_at', 'revalidated_at', 'finished_at', 'created_at', 'updated_at'],
            'objects' => ['id', 'item_id', 'object_key', 'kind', 'expected_sha256', 'expected_size', 'mime_type', 'write_state', 'create_state', 'cleanup_state', 'etag', 'version_id', 'write_intent_at', 'write_confirmed_at', 'cleanup_attempted_at', 'cleanup_finished_at', 'created_at', 'updated_at'],
        ];
        foreach ($expected as $suffix => $columns) {
            $table = 'media_backfill_'.$suffix;
            $this->assertTrue(Schema::hasTable($table));
            $this->assertEqualsCanonicalizing($columns, Schema::getColumnListing($table));
            foreach (Schema::getColumns($table) as $column) {
                $this->assertNotSame('enum', $column['type_name']);
            }
        }
        foreach ([['runs', ['run_id'], true], ['runs', ['state', 'started_at'], false],
            ['items', ['run_id', 'domain', 'entity_id'], true], ['items', ['run_id', 'phase'], false],
            ['items', ['master_key_hash', 'phase'], false], ['objects', ['item_id', 'object_key'], true],
            ['objects', ['cleanup_state', 'updated_at'], false]] as [$suffix, $columns, $unique]) {
            $indexes = Schema::getIndexes('media_backfill_'.$suffix);
            $this->assertTrue(collect($indexes)->contains(fn ($index) => $index['columns'] === $columns && $index['unique'] === $unique));
        }
        $createState = collect(Schema::getColumns('media_backfill_objects'))->firstWhere('name', 'create_state');
        $this->assertSame('varchar', $createState['type_name']);
        $this->assertTrue($createState['nullable']);
        $foreign = DB::select("SELECT TABLE_NAME, REFERENCED_TABLE_NAME, DELETE_RULE, UPDATE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'media_backfill_%' ORDER BY TABLE_NAME");
        $this->assertCount(2, $foreign);
        $this->assertSame('media_backfill_items', $foreign[0]->TABLE_NAME);
        $this->assertSame('media_backfill_runs', $foreign[0]->REFERENCED_TABLE_NAME);
        $this->assertSame('media_backfill_objects', $foreign[1]->TABLE_NAME);
        $this->assertSame('media_backfill_items', $foreign[1]->REFERENCED_TABLE_NAME);
        foreach ($foreign as $constraint) {
            $this->assertSame('RESTRICT', $constraint->DELETE_RULE);
            $this->assertSame('RESTRICT', $constraint->UPDATE_RULE);
        }
        [$journal, $run, $item, $object] = $this->plannedObject();
        foreach ([['media_backfill_runs', 'run_id', $run], ['media_backfill_items', 'id', $item]] as [$table, $key, $id]) {
            try {
                DB::table($table)->where($key, $id)->delete();
                $this->fail('RESTRICT must retain history.');
            } catch (QueryException $error) {
                $this->assertSame('23000', $error->errorInfo[0]);
            }
        }
        foreach ([['media_backfill_items', $journal->item($item)], ['media_backfill_objects', $journal->object($object)]] as [$table, $row]) {
            $values = (array) $row;
            unset($values['id']);
            try {
                DB::table($table)->insert($values);
                $this->fail('Unique index must reject duplicate.');
            } catch (QueryException $error) {
                $this->assertSame(1062, $error->errorInfo[1]);
            }
        }
    }

    public function test_migration_down_and_up_on_isolated_database(): void
    {
        $migration = require database_path('migrations/2026_09_09_000000_create_media_backfill_journal_tables.php');
        try {
            $migration->down();
            foreach (['runs', 'items', 'objects'] as $suffix) {
                $this->assertFalse(Schema::hasTable('media_backfill_'.$suffix));
            }
        } finally {
            $migration->up();
        }
        foreach (['runs', 'items', 'objects'] as $suffix) {
            $this->assertTrue(Schema::hasTable('media_backfill_'.$suffix));
        }
    }

    public function test_apply_run_reload_item_upsert_uniqueness_and_heartbeat(): void
    {
        $journal = app(ApplyJournal::class);
        $run = $journal->createApplyRun($this->identity(), $this->applySelection(), str_repeat('a', 40));
        $this->assertMatchesRegularExpression('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/', $run);
        $item = $journal->snapshot($run, $this->preflight());
        $this->assertSame($item, $journal->snapshot($run, $this->preflight()));
        $fresh = new ApplyJournal(app('db'));
        $this->assertSame('apply', $fresh->run($run)->mode);
        $this->assertSame($this->identity()->hash, $fresh->run($run)->storage_identity_hash);
        $this->assertSame(['domain' => 'news', 'after_id' => 0, 'limit' => 1000], json_decode($fresh->run($run)->options_json, true));
        $this->assertSame('news/'.self::SAFETY_UUID.'.jpg', $fresh->item($item)->master_key);
        $this->assertSame(hash('sha256', $fresh->item($item)->master_key), $fresh->item($item)->master_key_hash);
        $journal->heartbeat($run);
        $this->assertNotNull($fresh->run($run)->heartbeat_at);
        $this->assertCount(1, $fresh->activeRuns());
        $this->assertCount(1, $fresh->unfinishedItems($run));
        $journal->finishItem($item, ApplyResult::Skipped);
        $journal->finishRun($run, RunState::Completed, ['skipped' => 1]);
        $this->assertSame([], $fresh->activeRuns());
        $this->assertSame([], $fresh->unfinishedItems($run));
        $this->assertSafetyError(SafetyError::IllegalTransition, fn () => $journal->heartbeat($run));
        $this->assertSafetyError(SafetyError::IllegalTransition, fn () => $journal->finishRun($run, RunState::Failed));
    }

    public function test_manifest_exact_16_kib_boundary_and_hash_validation(): void
    {
        $journal = app(ApplyJournal::class);
        $run = $journal->createApplyRun($this->identity(), $this->applySelection());
        $preflight = $this->preflight();
        $json = str_pad($preflight->prepared->manifest->toJson(), ResponsiveManifest::MAX_BYTES, ' ');
        $id = $journal->snapshot($run, $preflight, candidateJson: $json);
        $this->assertSame(16384, strlen($journal->item($id)->candidate_manifest_json));
        $this->assertSame(hash('sha256', $json), $journal->item($id)->candidate_manifest_sha256);
        $this->assertSafetyError(SafetyError::InvalidInput, fn () => $journal->snapshot($run, $preflight, candidateJson: $json.' '));
        $this->assertSafetyError(SafetyError::InvalidInput, fn () => $journal->snapshot($run, $preflight, sourceSha256: 'not-a-hash'));
        $this->assertSafetyError(SafetyError::InvalidInput,
            fn () => $journal->createApplyRun($this->identity(), $this->applySelection(), 'not-a-revision'));
        [$target] = $this->variant();
        $this->assertSafetyError(SafetyError::InvalidInput, fn () => new TargetObject($target->key, $target->kind, 'bad', $target->size, $target->mimeType));
    }

    public function test_invalid_references_are_bounded_sanitized_and_never_serialized(): void
    {
        $journal = app(ApplyJournal::class);
        $run = $journal->createApplyRun($this->identity(), $this->applySelection());
        $secret = "https://user:secret@private.invalid/\n\0".str_repeat('x', 100000);
        $inputs = [$secret, ['private' => $secret], new class
        {
            public function __serialize(): array
            {
                throw new RuntimeException('Do not serialize me.');
            }
        }, null];
        foreach ($inputs as $index => $input) {
            $result = new PreflightResult(new ManagedMediaReference(ManagedMediaDomain::News, $index + 1, $input), PreflightClassification::InvalidReference, [InspectionReason::InvalidReference]);
            $id = $journal->snapshot($run, $result);
            $row = $journal->item($id);
            $this->assertNull($row->master_key);
            $this->assertLessThanOrEqual(160, strlen($row->reference_sample));
            $this->assertDoesNotMatchRegularExpression('/[\x00-\x1f]/', $row->reference_sample);
            $this->assertStringNotContainsString('secret', json_encode($row));
            $this->assertSame($row->master_key_hash, $journal->item($journal->snapshot($run, $result))->master_key_hash);
            if (is_string($input)) {
                $this->assertSame(hash('sha256', $input), $row->master_key_hash);
            }
        }
    }

    public function test_state_order_and_cleanup_fail_closed(): void
    {
        [$journal, $run, $item, $object, $target] = $this->plannedObject();
        $this->assertSafetyError(SafetyError::IllegalTransition, fn () => $journal->planObject($item, $target));
        $this->assertSafetyError(SafetyError::IllegalTransition, fn () => $journal->snapshot($run, $this->preflight()));
        $this->assertSafetyError(SafetyError::IllegalTransition, fn () => $journal->recordReceipt($object, new CreateReceipt(CreateState::Created)));
        $this->assertSafetyError(SafetyError::IllegalTransition, fn () => $journal->finishItem($item, ApplyResult::Published));
        $journal->commitIntent($object);
        $this->assertSame('intent', $journal->object($object)->write_state);
        $this->assertNull($journal->object($object)->create_state);
        $this->assertNotNull($journal->object($object)->write_intent_at);
        $this->assertSafetyError(SafetyError::IllegalTransition, fn () => $journal->commitIntent($object));
        $this->assertSafetyError(SafetyError::IllegalTransition, fn () => $journal->updateCleanup($object, CleanupState::Pending));
        $journal->recordReceipt($object, new CreateReceipt(CreateState::Created, 'etag', 'version'));
        $this->assertSafetyError(SafetyError::IllegalTransition, fn () => $journal->recordReceipt($object, new CreateReceipt(CreateState::Unknown)));
        $this->assertSafetyError(SafetyError::IllegalTransition, fn () => $journal->updateCleanup($object, CleanupState::Deleted));
        $journal->updateCleanup($object, CleanupState::Pending);
        $journal->updateCleanup($object, CleanupState::Unknown);
        $journal->updateCleanup($object, CleanupState::Pending);
        $journal->updateCleanup($object, CleanupState::Failed);
        $journal->updateCleanup($object, CleanupState::Pending);
        $journal->updateCleanup($object, CleanupState::Deleted);
        $this->assertNotNull($journal->object($object)->cleanup_finished_at);
        $this->assertSafetyError(SafetyError::IllegalTransition, fn () => $journal->updateCleanup($object, CleanupState::Pending));
        $journal->finishItem($item, ApplyResult::FailedCompensated);
        $journal->finishRun($run, RunState::Failed);
        $this->assertSame([], $journal->unresolvedObjects());
    }

    public function test_unknown_is_recoverable_even_on_terminal_item_and_run(): void
    {
        [$journal, $run, $item, $object] = $this->plannedObject();
        $journal->commitIntent($object);
        $journal->recordReceipt($object, new CreateReceipt(CreateState::Unknown));
        $this->assertSafetyError(SafetyError::IllegalTransition, fn () => $journal->finishItem($item, ApplyResult::FailedNoWrites));
        $journal->finishItem($item, ApplyResult::PublicationUnknown);
        $this->assertSafetyError(SafetyError::IllegalTransition, fn () => $journal->finishRun($run, RunState::Completed));
        $journal->finishRun($run, RunState::Interrupted);
        $this->assertCount(1, $journal->unresolvedObjects());
        $this->assertSame('unknown', $journal->unresolvedObjects()[0]->write_state);
        $this->assertSafetyError(SafetyError::IllegalTransition, fn () => $journal->recordReceipt($object, new CreateReceipt(CreateState::Created)));
    }

    public function test_real_commit_precedes_storage_and_no_domain_row_changes(): void
    {
        $news = NewsArticle::factory()->create();
        $before = $news->fresh()->getAttributes();
        $mutations = [];
        DB::listen(function ($query) use (&$mutations) {
            if (preg_match('/^(insert|update|delete)\\b/i', $query->sql)) {
                $mutations[] = $query->sql;
            }
        });
        [$journal, $run, $item, $object, $target, $bytes] = $this->plannedObject();
        $lock = app(MariaDbBackfillLock::class)->acquire($this->identity())->handle;
        $reader = app(ConnectionFactory::class)->make(DB::connection()->getConfig(), 'journal_visibility_test');
        $creator = Mockery::mock(ExclusiveObjectCreator::class);
        $creator->shouldReceive('create')->once()->andReturnUsing(function () use ($reader, $object) {
            $this->assertSame(0, DB::transactionLevel());
            $this->assertFalse(DB::connection()->getPdo()->inTransaction());
            $this->assertSame('intent', $reader->table('media_backfill_objects')->where('id', $object)->value('write_state'));

            return new CreateReceipt(CreateState::Created, 'etag', 'version');
        });
        try {
            $writer = new JournaledObjectWriter($journal, app(ApplyMaintenanceGuard::class), $creator, app('db'));
            $this->assertSame(CreateState::Created, $writer->create($object, $bytes, $lock)->state);
            $this->assertSame('created', $journal->object($object)->write_state);
            $this->assertSame('etag', $journal->object($object)->etag);
            $this->assertNotNull($journal->object($object)->write_confirmed_at);
            $this->assertSame($before, $news->fresh()->getAttributes());
            $this->assertNotEmpty($mutations);
            foreach ($mutations as $sql) {
                $this->assertMatchesRegularExpression('/^(insert into|update|delete from) `media_backfill_/', $sql);
            }
        } finally {
            $reader->disconnect();
            $lock->release();
        }
    }

    #[DataProvider('commitFailures')]
    public function test_commit_failure_before_intent_makes_zero_storage_calls_and_receipt_failure_preserves_intent(int $failCommit): void
    {
        [$journal, $run, $item, $object, $target, $bytes] = $this->plannedObject();
        $lock = app(MariaDbBackfillLock::class)->acquire($this->identity())->handle;
        $commits = 0;
        DB::connection()->getEventDispatcher()->listen(TransactionCommitting::class, function () use (&$commits, $failCommit) {
            if (++$commits === $failCommit) {
                throw new RuntimeException('secret DSN must not escape');
            }
        });
        $creator = Mockery::mock(ExclusiveObjectCreator::class);
        if ($failCommit === 1) {
            $creator->shouldNotReceive('create');
        } else {
            $realCreator = app(ExclusiveObjectCreator::class);
            $creator->shouldReceive('create')->once()->andReturnUsing(fn ($target, $bytes) => $realCreator->create($target, $bytes));
        }
        try {
            $writer = new JournaledObjectWriter($journal, app(ApplyMaintenanceGuard::class), $creator, app('db'));
            $this->assertSafetyError($failCommit === 1 ? SafetyError::JournalUnavailable : SafetyError::PublicationUnknown,
                fn () => $writer->create($object, $bytes, $lock));
            $this->assertSame($failCommit === 1 ? 'planned' : 'intent', (new ApplyJournal(app('db')))->object($object)->write_state);
            $this->assertNull($journal->object($object)->write_confirmed_at);
            $this->assertNull($journal->object($object)->create_state);
            $this->assertSame(0, DB::transactionLevel());
            $this->assertFalse(DB::connection()->getPdo()->inTransaction());
            $reader = app(ConnectionFactory::class)->make(DB::connection()->getConfig(), 'failed_commit_visibility');
            try {
                $this->assertSame($failCommit === 1 ? 'planned' : 'intent', $reader->table('media_backfill_objects')->where('id', $object)->value('write_state'));
            } finally {
                $reader->disconnect();
            }
            if ($failCommit === 2) {
                $this->assertSame($bytes, file_get_contents($this->safetyRoot.'/'.$target->key));
                $this->assertCount(1, $journal->unresolvedObjects());
            } else {
                $this->assertFileDoesNotExist($this->safetyRoot.'/'.$target->key);
            }
        } finally {
            DB::connection()->getEventDispatcher()->forget(TransactionCommitting::class);
            $lock->release();
        }
    }

    public static function commitFailures(): array
    {
        return [[1], [2]];
    }

    public function test_ambient_transaction_guard_and_lost_lock_prevent_storage(): void
    {
        [$journal, $run, $item, $object, $target, $bytes] = $this->plannedObject();
        $lock = app(MariaDbBackfillLock::class)->acquire($this->identity())->handle;
        $creator = Mockery::mock(ExclusiveObjectCreator::class);
        $creator->shouldNotReceive('create');
        $writer = new JournaledObjectWriter($journal, app(ApplyMaintenanceGuard::class), $creator, app('db'));
        try {
            DB::beginTransaction();
            $this->assertSafetyError(SafetyError::AmbientTransaction, fn () => $writer->create($object, $bytes, $lock));
            DB::rollBack();
            $this->assertSame('planned', $journal->object($object)->write_state);
            $lock->release();
            $this->assertSafetyError(SafetyError::LockLost, fn () => $writer->create($object, $bytes, $lock));
            $this->assertSame('planned', $journal->object($object)->write_state);
        } finally {
            $lock->close();
        }
    }

    #[DataProvider('storageReceipts')]
    public function test_writer_preserves_exact_create_outcome_and_distinguishes_failure_from_collision(CreateState $state, string $expected): void
    {
        [$journal, $run, $item, $object, $target, $bytes] = $this->plannedObject();
        $lock = app(MariaDbBackfillLock::class)->acquire($this->identity())->handle;
        $creator = Mockery::mock(ExclusiveObjectCreator::class);
        $creator->shouldReceive('create')->once()->andReturn(new CreateReceipt($state));
        try {
            $writer = new JournaledObjectWriter($journal, app(ApplyMaintenanceGuard::class), $creator, app('db'));
            $this->assertSame($state, $writer->create($object, $bytes, $lock)->state);
            $row = (new ApplyJournal(app('db')))->object($object);
            $this->assertSame($expected, $row->write_state);
            $this->assertSame($state->value, $row->create_state);
            if ($state === CreateState::Failed) {
                $this->assertSafetyError(SafetyError::IllegalTransition, fn () => $journal->finishItem($item, ApplyResult::CollisionDetected));
                $journal->finishItem($item, ApplyResult::FailedNoWrites);
                $this->assertSame('failed_no_writes', $journal->item($item)->apply_result);
            } elseif ($state === CreateState::Rejected) {
                $journal->finishItem($item, ApplyResult::CollisionDetected);
                $this->assertSame('collision_detected', $journal->item($item)->apply_result);
            }
        } finally {
            $lock->release();
        }
    }

    public function test_published_requires_all_planned_candidate_objects_and_terminal_states_cannot_go_backwards(): void
    {
        $journal = app(ApplyJournal::class);
        $run = $journal->createApplyRun($this->identity(), $this->applySelection());
        $preflight = $this->preflight();
        $item = $journal->snapshot($run, $preflight);
        $manifest = $preflight->prepared->manifest->toJson();
        $manifestTarget = new TargetObject($journal->item($item)->manifest_key,
            ObjectKind::Manifest, hash('sha256', $manifest), strlen($manifest), 'application/json');
        $manifestId = $journal->planObject($item, $manifestTarget);
        $variant = $preflight->prepared->manifest->variants[0];
        $variantBytes = $preflight->prepared->variants[0]->bytes;
        $variantTarget = new TargetObject($variant->key,
            ObjectKind::Variant, hash('sha256', $variantBytes), strlen($variantBytes), $variant->mimeType);
        $variantId = $journal->planObject($item, $variantTarget);
        $this->assertSafetyError(SafetyError::IllegalTransition, fn () => $journal->commitIntent($manifestId));
        $journal->markRevalidated($item);
        $journal->commitIntent($variantId);
        $journal->recordReceipt($variantId, new CreateReceipt(CreateState::Created));
        $this->assertSafetyError(SafetyError::IllegalTransition, fn () => $journal->finishItem($item, ApplyResult::Published));
        $journal->commitIntent($manifestId);
        $journal->recordReceipt($manifestId, new CreateReceipt(CreateState::Created));
        $journal->finishItem($item, ApplyResult::Published);
        $this->assertSame('published', $journal->item($item)->apply_result);
        $this->assertSafetyError(SafetyError::IllegalTransition, fn () => $journal->markRevalidated($item));
        $this->assertSafetyError(SafetyError::IllegalTransition, fn () => $journal->finishItem($item, ApplyResult::Skipped));
        $journal->finishRun($run, RunState::Completed, ['published' => 1]);
        $this->assertSame('completed', $journal->run($run)->state);
    }

    #[DataProvider('otherApplyOutcomes')]
    public function test_other_agreed_apply_outcomes_and_cleanup_history_after_terminal_run(ApplyResult $outcome): void
    {
        [$journal, $run, $item, $object] = $this->plannedObject();
        if ($outcome === ApplyResult::CollisionDetected) {
            $journal->commitIntent($object);
            $journal->recordReceipt($object, new CreateReceipt(CreateState::Rejected));
        }
        if ($outcome === ApplyResult::FailedCleanupIncomplete) {
            $journal->commitIntent($object);
            $journal->recordReceipt($object, new CreateReceipt(CreateState::Created));
            $journal->updateCleanup($object, CleanupState::Pending);
            $journal->updateCleanup($object, CleanupState::Failed);
        }
        $journal->finishItem($item, $outcome);
        $journal->finishRun($run, RunState::Failed);
        $this->assertSame($outcome->value, $journal->item($item)->apply_result);
        if ($outcome === ApplyResult::FailedCleanupIncomplete) {
            $this->assertCount(1, $journal->unresolvedObjects());
            $journal->updateCleanup($object, CleanupState::Pending);
            $journal->updateCleanup($object, CleanupState::Deleted);
            $this->assertSame([], $journal->unresolvedObjects());
        }
    }

    public static function otherApplyOutcomes(): array
    {
        return [[ApplyResult::ReferenceChanged], [ApplyResult::FailedNoWrites], [ApplyResult::CollisionDetected], [ApplyResult::FailedCleanupIncomplete]];
    }

    #[DataProvider('guardFailures')]
    public function test_maintenance_failure_before_or_after_intent_prevents_any_storage(int $failureCall): void
    {
        [$journal, $run, $item, $object, $target, $bytes] = $this->plannedObject();
        $lock = app(MariaDbBackfillLock::class)->acquire($this->identity())->handle;
        $creator = Mockery::mock(ExclusiveObjectCreator::class);
        $creator->shouldNotReceive('create');
        $guard = Mockery::mock(ApplyMaintenanceGuard::class);
        $calls = 0;
        $guard->shouldReceive('assertAllowed')->times($failureCall)->andReturnUsing(function () use (&$calls, $failureCall) {
            if (++$calls === $failureCall) {
                throw new BackfillSafetyException(SafetyError::MaintenanceRequired);
            }
        });
        try {
            $writer = new JournaledObjectWriter($journal, $guard, $creator, app('db'));
            $this->assertSafetyError(SafetyError::MaintenanceRequired, fn () => $writer->create($object, $bytes, $lock));
            $this->assertSame($failureCall === 1 ? 'planned' : 'intent', $journal->object($object)->write_state);
        } finally {
            $lock->release();
        }
    }

    public static function guardFailures(): array
    {
        return [[1], [2]];
    }

    public function test_invalid_bytes_and_environment_mismatch_prevent_intent_and_storage(): void
    {
        [$journal, $run, $item, $object, $target, $bytes] = $this->plannedObject();
        $lock = app(MariaDbBackfillLock::class)->acquire($this->identity())->handle;
        $creator = Mockery::mock(ExclusiveObjectCreator::class);
        $creator->shouldNotReceive('create');
        try {
            $writer = new JournaledObjectWriter($journal, app(ApplyMaintenanceGuard::class), $creator, app('db'));
            $this->assertSafetyError(SafetyError::InvalidInput, fn () => $writer->create($object, $bytes.'x', $lock));
            mkdir($this->safetyRoot.'/different');
            config()->set('filesystems.disks.media_local.root', $this->safetyRoot.'/different');
            $this->assertSafetyError(SafetyError::IdentityMismatch, fn () => $writer->create($object, $bytes, $lock));
            $this->assertSame('planned', $journal->object($object)->write_state);
        } finally {
            $lock->release();
        }
    }

    public static function storageReceipts(): array
    {
        return [[CreateState::Created, 'created'], [CreateState::Rejected, 'rejected'], [CreateState::Unknown, 'unknown'], [CreateState::Failed, 'rejected']];
    }
}
