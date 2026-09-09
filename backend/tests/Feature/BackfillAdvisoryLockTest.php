<?php

namespace Tests\Feature;

use App\Services\Media\Backfill\Safety\AdvisoryLockHandle;
use App\Services\Media\Backfill\Safety\LockAcquireState;
use App\Services\Media\Backfill\Safety\MariaDbBackfillLock;
use App\Services\Media\Backfill\Safety\SafetyError;
use Illuminate\Database\Connection;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BackfillSafetyFixtures;
use Tests\TestCase;

class BackfillAdvisoryLockTest extends TestCase
{
    use BackfillSafetyFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('galotxas_testing', DB::connection()->getDatabaseName());
        $this->assertSame('test-db', DB::connection()->getConfig('host'));
        $this->setupSafetyStorage();
    }

    protected function tearDown(): void
    {
        $this->cleanupSafetyStorage();
        parent::tearDown();
    }

    public function test_two_real_sessions_are_single_flight_and_release_is_verified(): void
    {
        $locks = app(MariaDbBackfillLock::class);
        $first = $locks->acquire($this->identity());
        $second = null;
        try {
            $this->assertSame(LockAcquireState::Acquired, $first->state);
            $first->handle->assertOwned();
            $this->assertNotSame((string) DB::selectOne('SELECT CONNECTION_ID() AS id')->id, $first->handle->connectionId);
            $busy = $locks->acquire($this->identity());
            $this->assertSame(LockAcquireState::Busy, $busy->state);
            $this->assertNull($busy->handle);
            $first->handle->release();
            $first->handle->release();
            $this->assertSafetyError(SafetyError::LockLost, fn () => $first->handle->assertOwned());
            $second = $locks->acquire($this->identity());
            $this->assertSame(LockAcquireState::Acquired, $second->state);
            $this->assertNotSame($first->handle->connectionId, $second->handle->connectionId);
            $first->handle->release(); // A repeated successful release cannot affect the new owner.
            $second->handle->assertOwned();
            $second->handle->release();
            $this->assertNull(DB::selectOne('SELECT IS_USED_LOCK(?) AS owner', [$this->identity()->lockName()])->owner);
        } finally {
            $first->handle?->close();
            $second?->handle?->close();
        }
    }

    public function test_dropping_dedicated_connection_releases_lock_without_release_query(): void
    {
        $first = app(MariaDbBackfillLock::class)->acquire($this->identity());
        $first->handle->close();
        $second = app(MariaDbBackfillLock::class)->acquire($this->identity());
        try {
            $this->assertSame(LockAcquireState::Acquired, $second->state);
            $this->assertSafetyError(SafetyError::LockLost, fn () => $first->handle->assertOwned());
            $this->assertSafetyError(SafetyError::LockLost, fn () => $first->handle->release());
            $second->handle->assertOwned();
        } finally {
            $second->handle?->release();
        }
    }

    public function test_killed_session_is_lost_and_never_silently_reacquired(): void
    {
        $first = app(MariaDbBackfillLock::class)->acquire($this->identity());
        DB::unprepared('KILL CONNECTION '.(int) $first->handle->connectionId);
        $this->assertSafetyError(SafetyError::LockLost, fn () => $first->handle->assertOwned());
        $second = app(MariaDbBackfillLock::class)->acquire($this->identity());
        try {
            $this->assertSame(LockAcquireState::Acquired, $second->state);
            $this->assertSafetyError(SafetyError::LockLost, fn () => $first->handle->assertOwned());
            $this->assertSafetyError(SafetyError::LockLost, fn () => $first->handle->release());
            $second->handle->assertOwned();
        } finally {
            $first->handle->close();
            $second->handle?->release();
        }
    }

    public function test_destructor_drops_session_and_factory_failure_is_typed_without_secrets(): void
    {
        $first = app(MariaDbBackfillLock::class)->acquire($this->identity());
        $this->assertSame(LockAcquireState::Acquired, $first->state);
        unset($first);
        $second = app(MariaDbBackfillLock::class)->acquire($this->identity());
        $this->assertSame(LockAcquireState::Acquired, $second->state);
        $second->handle->release();
        $factory = \Mockery::mock(ConnectionFactory::class);
        $factory->shouldReceive('make')->once()->andThrow(new \RuntimeException('secret DSN'));
        $failure = (new MariaDbBackfillLock(app('db'), $factory))->acquire($this->identity());
        $this->assertSame(LockAcquireState::Failed, $failure->state);
        $this->assertNull($failure->handle);
    }

    #[DataProvider('releaseFailures')]
    public function test_release_failure_after_verified_ownership_remains_release_failed_and_closes_resources(int|string|null $result): void
    {
        $connection = \Mockery::mock(Connection::class);
        $connection->shouldReceive('disconnect')->once();
        $pdo = \Mockery::mock(\PDO::class);
        $connectionId = \Mockery::mock(\PDOStatement::class);
        $connectionId->shouldReceive('fetchColumn')->once()->andReturn('17');
        $pdo->shouldReceive('query')->once()->with('SELECT CONNECTION_ID()')->andReturn($connectionId);
        $owner = \Mockery::mock(\PDOStatement::class);
        $owner->shouldReceive('execute')->once()->with([$this->identity()->lockName()])->andReturn(true);
        $owner->shouldReceive('fetchColumn')->once()->andReturn('17');
        $pdo->shouldReceive('prepare')->once()->with('SELECT IS_USED_LOCK(?)')->andReturn($owner);
        $release = \Mockery::mock(\PDOStatement::class);
        $pdo->shouldReceive('prepare')->once()->with('SELECT RELEASE_LOCK(?)')->andReturn($release);
        if ($result === 'exception') {
            $release->shouldReceive('execute')->once()->with([$this->identity()->lockName()])->andThrow(new \RuntimeException('private failure'));
        } else {
            $release->shouldReceive('execute')->once()->with([$this->identity()->lockName()])->andReturn(true);
            $release->shouldReceive('fetchColumn')->once()->andReturn($result);
        }
        $handle = new AdvisoryLockHandle($connection, $pdo, $this->identity(), '17');
        $this->assertSafetyError(SafetyError::LockReleaseFailed, fn () => $handle->release());
        $this->assertSafetyError(SafetyError::LockLost, fn () => $handle->release());
    }

    public static function releaseFailures(): array
    {
        return [[null], [0], ['exception']];
    }

    public function test_wrong_identity_is_typed_infrastructure_failure(): void
    {
        $identity = $this->identity();
        config()->set('database.connections.mariadb.database', 'different');
        DB::purge();
        $result = app(MariaDbBackfillLock::class)->acquire($identity);
        $this->assertSame(LockAcquireState::Failed, $result->state);
        $this->assertNull($result->handle);
    }
}
