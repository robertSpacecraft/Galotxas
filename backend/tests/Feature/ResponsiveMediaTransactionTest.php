<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Media\ImagePreparationPolicy;
use App\Services\Media\ResponsiveImagePreparer;
use App\Services\Media\ResponsiveImageProfile;
use App\Services\Media\ResponsiveMediaLifecycle;
use App\Services\Media\ResponsiveMediaStorage;
use App\Services\Media\StoredResponsiveSet;
use Illuminate\Database\DatabaseTransactionRecord;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PDO;
use ReflectionProperty;
use RuntimeException;
use Tests\Concerns\InteractsWithResponsiveMedia;
use Tests\TestCase;
use WeakReference;

class ResponsiveMediaTransactionTest extends TestCase
{
    use DatabaseTruncation;
    use InteractsWithResponsiveMedia;

    protected function tearDown(): void
    {
        try {
            if (DB::transactionLevel() > 0) {
                DB::rollBack(0);
            }
            $this->truncateTablesForAllConnections();
        } finally {
            parent::tearDown();
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('galotxas_testing', DB::connection()->getDatabaseName());
        $this->assertSame('test-db', DB::connection()->getConfig('host'));
        $this->assertSame(DatabaseTransactionsManager::class, get_class(app('db.transactions')));
        $this->assertSame(0, DB::transactionLevel());
        config()->set('media.disk', 'media_local');
        Storage::fake('media_local');
    }

    public function test_installed_framework_commit_and_rollback_semantics(): void
    {
        $events = [];
        DB::afterCommit(function () use (&$events) {
            $events[] = 'immediate';
        });
        DB::beginTransaction();
        DB::afterRollBack(function () use (&$events) {
            $events[] = 'root rollback';
        });
        DB::beginTransaction();
        DB::afterCommit(function () use (&$events) {
            $events[] = 'child commit';
        });
        DB::afterRollBack(function () use (&$events) {
            $events[] = 'child rollback';
        });
        DB::commit();
        $this->assertSame(['immediate'], $events);
        DB::rollBack();
        // Committed child rollback callbacks are discarded by this installed version.
        $this->assertSame(['immediate', 'root rollback'], $events);
        DB::transaction(function () use (&$events) {
            DB::transaction(function () use (&$events) {
                DB::afterCommit(function () use (&$events) {
                    $events[] = 'outer commit';
                });
            });
            $this->assertNotContains('outer commit', $events);
        });
        $this->assertSame(['immediate', 'root rollback', 'outer commit'], $events);
        $this->assertNoRecords();
    }

    public function test_successful_replace_without_ambient_transaction(): void
    {
        [$user, $old, $new] = $this->replacement();
        $this->replace($user, $new);
        $this->assertSame($new->masterKey, $user->fresh()->profile_photo_path);
        $this->assertSet($old, false);
        $this->assertSet($new, true);
        $this->assertNoRecords();
    }

    public function test_database_failure_compensates_only_new_set_and_preserves_exception_identity(): void
    {
        [$user, $old, $new] = $this->replacement();
        $failure = new RuntimeException('domain failure');
        $attempts = 0;
        try {
            $this->replace($user, $new, function () use ($failure, &$attempts) {
                $attempts++;
                throw $failure;
            }, 3);
            $this->fail('Expected failure.');
        } catch (RuntimeException $caught) {
            $this->assertSame($failure, $caught);
        }
        $this->assertSame(1, $attempts);
        $this->assertSame($old->masterKey, $user->fresh()->profile_photo_path);
        $this->assertSet($old, true);
        $this->assertSet($new, false);
        $this->assertNoRecords();
    }

    public function test_compensation_failure_does_not_mask_domain_exception_or_stop_other_deletes(): void
    {
        [$user, $old, $new] = $this->replacement();
        $failure = new RuntimeException('original domain exception');
        $this->failMediaDeletion([$new->masterKey]);
        try {
            $this->replace($user, $new, fn () => throw $failure);
            $this->fail('Expected the domain exception.');
        } catch (RuntimeException $caught) {
            $this->assertSame($failure, $caught);
        }
        $this->assertSame($old->masterKey, $user->fresh()->profile_photo_path);
        $this->assertSet($old, true);
        Storage::disk('media_local')->assertExists($new->masterKey);
        Storage::disk('media_local')->assertMissing($new->manifestKey);
        foreach ($new->manifest->variants as $variant) {
            Storage::disk('media_local')->assertMissing($variant->key);
        }
        $this->assertNoRecords();
    }

    public function test_commit_retry_discards_failed_attempt_cleanup_and_final_failure_releases_records(): void
    {
        foreach ([false, true] as $failAll) {
            [$user, $old, $new] = $this->replacement();
            $attempts = 0;
            $lastFailure = null;
            $records = [];
            $dispatcher = DB::connection()->getEventDispatcher();
            $dispatcher->listen(TransactionCommitting::class, function () use (&$attempts, $failAll, &$lastFailure, &$records) {
                $attempts++;
                $records[] = WeakReference::create($this->currentTransaction());
                if ($attempts === 1 || $failAll) {
                    // A real DB error in Laravel's committing phase exercises its separate catch.
                    try {
                        DB::statement("SIGNAL SQLSTATE '40001' SET MYSQL_ERRNO = 1213, MESSAGE_TEXT = 'Deadlock found when trying to get lock'");
                    } catch (QueryException $exception) {
                        $lastFailure = $exception;
                        throw $exception;
                    }
                }
            });
            try {
                $this->replace($user, $new, attempts: 3);
                $this->assertFalse($failAll);
            } catch (QueryException $exception) {
                $this->assertTrue($failAll);
                $this->assertSame(1213, $exception->errorInfo[1]);
                $this->assertSame($lastFailure, $exception);
            } finally {
                $dispatcher->forget(TransactionCommitting::class);
            }
            $this->assertSame($failAll ? 3 : 2, $attempts);
            $this->assertFalse(DB::connection()->getPdo()->inTransaction());
            $this->assertSame($failAll ? $old->masterKey : $new->masterKey, $user->fresh()->profile_photo_path);
            $this->assertSet($old, $failAll);
            $this->assertSet($new, ! $failAll);
            $this->assertNoRecords();
            foreach ($records as $record) {
                $this->assertNull($record->get());
            }
        }
    }

    public function test_repeated_commit_retries_release_records_and_callbacks_before_unrelated_transactions(): void
    {
        $real = Storage::disk('media_local');
        $disk = Mockery::mock($real)->makePartial();
        $puts = [];
        $deletes = [];
        $cleanupStates = [];
        $disk->shouldReceive('put')->andReturnUsing(function ($key, $bytes, $options) use ($real, &$puts) {
            $puts[] = $key;

            return $real->put($key, $bytes, $options);
        });
        $disk->shouldReceive('delete')->andReturnUsing(function ($key) use ($real, &$deletes, &$cleanupStates) {
            $deletes[] = $key;
            $cleanupStates[] = [DB::transactionLevel(), DB::connection()->getPdo()->inTransaction()];

            return $real->delete($key);
        });
        Storage::set('media_local', $disk);
        $dispatcher = DB::connection()->getEventDispatcher();
        $listeners = $dispatcher->getRawListeners();
        $retained = [];
        $commits = [];
        $rollbacks = [];

        for ($operation = 0; $operation < 5; $operation++) {
            $puts = $deletes = $cleanupStates = [];
            [$user, $old, $new] = $this->replacement();
            $attempts = 0;
            $dispatcher->listen(TransactionCommitting::class, function () use ($operation, &$attempts, &$retained, &$commits, &$rollbacks, &$deletes, $old, $new) {
                $attempt = ++$attempts;
                $this->assertSet($old, true);
                $this->assertSet($new, true);
                $this->assertSame([], $deletes);
                $record = $this->currentTransaction();
                $this->assertNotNull($record);
                $this->assertNull($record->parent);
                $this->assertCount(1, $record->getCallbacks()); // The lifecycle's commit marker.
                DB::afterCommit(static function () use ($operation, $attempt, &$commits): void {
                    $commits[] = [$operation, $attempt];
                });
                DB::afterRollBack(static function () use ($operation, $attempt, &$rollbacks): void {
                    $rollbacks[] = [$operation, $attempt];
                });
                foreach ([$record, ...$record->getCallbacks(), ...$record->getCallbacksForRollback()] as $capture) {
                    $retained[] = WeakReference::create($capture);
                }
                if ($attempt < 3) {
                    DB::statement("SIGNAL SQLSTATE '40001' SET MYSQL_ERRNO = 1213, MESSAGE_TEXT = 'Deadlock found when trying to get lock'");
                }
            });
            try {
                $this->replace($user, $new, attempts: 3);
            } finally {
                $dispatcher->forget(TransactionCommitting::class);
            }

            $this->assertSame(3, $attempts);
            $this->assertSame([$operation, 3], $commits[$operation]);
            $this->assertCount($operation + 1, $commits);
            $this->assertSame([[$operation, 1], [$operation, 2]], array_slice($rollbacks, -2));
            $this->assertCount(2 * ($operation + 1), $rollbacks);
            $this->assertEqualsCanonicalizing([
                $old->masterKey, $old->manifestKey, ...array_column($old->manifest->variants, 'key'),
                $new->masterKey, $new->manifestKey, ...array_column($new->manifest->variants, 'key'),
            ], $puts); // Each old/new object was written once, across all three attempts.
            $this->assertCount(6, $deletes); // Avatar: manifest, two widths x two formats, master.
            $this->assertCount(6, array_unique($deletes));
            $this->assertSame($old->masterKey, end($deletes));
            $this->assertSame(array_fill(0, 6, [0, false]), $cleanupStates);
            $this->assertNoRecords();
            foreach ($retained as $reference) {
                $this->assertNull($reference->get(), 'A completed attempt retained a record or callback.');
            }

            $completed = [$commits, $rollbacks, $puts, $deletes];
            $files = $real->allFiles();
            foreach ([true, false] as $commit) {
                $unrelated = User::factory()->create(['name' => 'Before']);
                DB::beginTransaction();
                $unrelated->update(['name' => 'After']);
                DB::beginTransaction();
                $commit ? DB::commit() : DB::rollBack();
                $commit ? DB::commit() : DB::rollBack();
                $this->assertSame($commit ? 'After' : 'Before', $unrelated->fresh()->name);
                $this->assertNoRecords();
                $this->assertSame($completed, [$commits, $rollbacks, $puts, $deletes]);
                $this->assertSame($files, $real->allFiles());
                $this->assertSame($new->masterKey, $user->fresh()->profile_photo_path);
                $this->assertSet($old, false);
                $this->assertSet($new, true);
            }
        }
        $this->assertSame($listeners, $dispatcher->getRawListeners());
    }

    public function test_failed_nested_mutation_preserves_the_callers_transaction_and_callbacks(): void
    {
        [$user, $old, $new] = $this->replacement();
        $failure = new RuntimeException('nested failure');
        $commits = 0;
        $rollbacks = 0;
        DB::beginTransaction();
        $outer = $this->currentTransaction();
        DB::afterCommit(function () use (&$commits) {
            $commits++;
        });
        DB::afterRollBack(function () use (&$rollbacks) {
            $rollbacks++;
        });
        try {
            $this->replace($user, $new, fn () => throw $failure, 3);
            $this->fail('Expected the nested failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame($failure, $exception);
        }
        $this->assertSame(1, DB::transactionLevel());
        $this->assertTrue(DB::connection()->getPdo()->inTransaction());
        $this->assertSame($outer, $this->currentTransaction());
        $this->assertSame([0, 0], [$commits, $rollbacks]);
        $this->assertSame($old->masterKey, $user->fresh()->profile_photo_path);
        $this->assertSet($old, true);
        $this->assertSet($new, false);
        DB::commit();
        $this->assertSame([1, 0], [$commits, $rollbacks]);
        $this->assertNoRecords();
    }

    public function test_ambient_commit_defers_old_cleanup_until_root_commit(): void
    {
        [$user, $old, $new] = $this->replacement();
        DB::beginTransaction();
        DB::beginTransaction();
        $this->replace($user, $new);
        $this->assertSet($old, true);
        DB::commit();
        $this->assertSet($old, true);
        DB::commit();
        $this->assertSet($old, false);
        $this->assertSet($new, true);
        $this->assertSame($new->masterKey, $user->fresh()->profile_photo_path);
        $this->assertNoRecords();
    }

    public function test_ambient_root_rollback_compensates_even_after_intermediate_savepoint_commit(): void
    {
        [$user, $old, $new] = $this->replacement();
        DB::beginTransaction();
        DB::beginTransaction();
        $this->replace($user, $new);
        DB::commit();
        $this->assertSet($new, true);
        $this->assertSet($old, true);
        DB::rollBack();
        $this->assertSame($old->masterKey, $user->fresh()->profile_photo_path);
        $this->assertSet($old, true);
        $this->assertSet($new, false);
        $this->assertNoRecords();
    }

    public function test_savepoint_rollback_compensates_once_and_later_sibling_can_commit(): void
    {
        [$user, $old, $new] = $this->replacement();
        DB::beginTransaction();
        DB::beginTransaction();
        $this->replace($user, $new);
        DB::rollBack();
        $this->assertSet($old, true);
        $this->assertSet($new, false);
        $this->assertSame($old->masterKey, $user->fresh()->profile_photo_path);
        $next = $this->stored();
        $this->replace($user, $next);
        DB::beginTransaction();
        DB::rollBack(); // An unrelated sibling rollback must not compensate next.
        $this->assertSet($next, true);
        DB::commit();
        $this->assertSet($old, false);
        $this->assertSet($next, true);
        $this->assertNoRecords();
    }

    public function test_real_mariadb_lock_timeout_retries_without_compensating_or_reuploading(): void
    {
        $this->lockTimeoutRetry(false);
    }

    public function test_final_real_mariadb_retry_failure_compensates_the_new_set(): void
    {
        $this->lockTimeoutRetry(true);
    }

    private function lockTimeoutRetry(bool $failAll): void
    {
        [$user, $old, $new] = $this->replacement();
        $blocker = User::factory()->create();
        $config = DB::connection()->getConfig();
        $other = new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$config['database'], $config['username'], $config['password']);
        $other->beginTransaction();
        $other->query('SELECT id FROM users WHERE id = '.(int) $blocker->id.' FOR UPDATE');
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
        $attempts = 0;
        try {
            $this->replace($user, $new, function () use (&$attempts, $other, $blocker, $old, $new, $failAll) {
                $attempts++;
                $this->assertSet($old, true);
                $this->assertSet($new, true);
                try {
                    DB::table('users')->where('id', $blocker->id)->update(['name' => 'locked']);
                } catch (QueryException $exception) {
                    $this->assertSame(1205, $exception->errorInfo[1]);
                    if (! $failAll) {
                        $other->rollBack();
                    }
                    throw $exception;
                }
            }, 3);
            $this->assertFalse($failAll);
        } catch (QueryException $exception) {
            $this->assertTrue($failAll);
            $this->assertSame(1205, $exception->errorInfo[1]);
        } finally {
            if ($other->inTransaction()) {
                $other->rollBack();
            }
            DB::statement('SET SESSION innodb_lock_wait_timeout = DEFAULT');
        }
        $this->assertSame($failAll ? 3 : 2, $attempts);
        $this->assertSame($failAll ? $old->masterKey : $new->masterKey, $user->fresh()->profile_photo_path);
        $this->assertSet($old, $failAll);
        $this->assertSet($new, ! $failAll);
        $this->assertNoRecords();
    }

    public function test_callbacks_do_not_accumulate_or_touch_later_operations(): void
    {
        $listeners = DB::connection()->getEventDispatcher()->getRawListeners();
        for ($index = 0; $index < 5; $index++) {
            [$user, $old, $new] = $this->replacement();
            DB::beginTransaction();
            $this->replace($user, $new);
            $index % 2 === 0 ? DB::commit() : DB::rollBack();
            $this->assertNoRecords();
            $this->assertSet($old, $index % 2 !== 0);
            $this->assertSet($new, $index % 2 === 0);
        }
        $this->assertSame($listeners, DB::connection()->getEventDispatcher()->getRawListeners());
    }

    private function replace(User $user, StoredResponsiveSet $new, ?callable $afterSave = null, int $attempts = 1): void
    {
        app(ResponsiveMediaLifecycle::class)->mutate($new, function (callable $obsolete) use ($user, $new, $afterSave) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $obsolete($locked->profile_photo_path, ResponsiveImageProfile::Avatar);
            $locked->forceFill(['profile_photo_path' => $new->masterKey])->save();
            $afterSave?->__invoke();
        }, $attempts);
    }

    private function replacement(): array
    {
        $old = $this->stored();
        $user = User::factory()->create();
        $user->forceFill(['profile_photo_path' => $old->masterKey])->save();

        return [$user, $old, $this->stored()];
    }

    private function stored(): StoredResponsiveSet
    {
        return app(ResponsiveMediaStorage::class)->store(app(ResponsiveImagePreparer::class)->prepare(
            UploadedFile::fake()->image('photo.png', 800, 400), ResponsiveImageProfile::Avatar, ImagePreparationPolicy::Photo,
        ));
    }

    private function assertSet(StoredResponsiveSet $set, bool $exists): void
    {
        foreach ([$set->masterKey, $set->manifestKey, ...array_column($set->manifest->variants, 'key')] as $key) {
            $this->assertSame($exists, Storage::disk('media_local')->exists($key));
        }
    }

    private function assertNoRecords(): void
    {
        $this->assertSame(0, DB::transactionLevel());
        $this->assertFalse(DB::connection()->getPdo()->inTransaction());
        $this->assertCount(0, app('db.transactions')->getPendingTransactions());
        $this->assertCount(0, app('db.transactions')->getCommittedTransactions());
        $this->assertNull($this->currentTransaction());
    }

    private function currentTransaction(): ?DatabaseTransactionRecord
    {
        $current = (new ReflectionProperty(DatabaseTransactionsManager::class, 'currentTransaction'))
            ->getValue(app('db.transactions'));

        return $current[DB::connection()->getName()] ?? null;
    }
}
