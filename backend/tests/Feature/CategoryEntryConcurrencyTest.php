<?php

namespace Tests\Feature;

use App\Exceptions\CategoryEntryIntegrityException;
use App\Models\CategoryEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Concerns\CreatesCategoryParticipantFixtures;
use Tests\TestCase;

/**
 * Real concurrent processes against the isolated MariaDB, coordinated with file
 * barriers like OfficialResultConcurrencyTest. The first process performs its
 * write and keeps the transaction open; the second one starts while the first
 * still holds its locks, so it genuinely waits and then observes the commit.
 */
class CategoryEntryConcurrencyTest extends TestCase
{
    use CreatesCategoryParticipantFixtures;
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        try {
            $this->truncateTablesForAllConnections();
        } finally {
            parent::tearDown();
        }
    }

    public function test_concurrent_admin_api_creations_of_one_identity_yield_one_entry_and_a_controlled_422(): void
    {
        $category = $this->categoryOfType('singles');
        $player = $this->registeredPlayer($category);
        $payload = json_encode(['entry_type' => 'player', 'player_id' => $player->id], JSON_THROW_ON_ERROR);

        [$first, $second] = $this->race(
            'api_store_entry',
            $category->id,
            $payload,
            secondBlocksOn: 'for update',
        );

        $this->assertSame('ok', $first['status']);
        $this->assertSame(201, $first['http']);
        $this->assertSame('approved', $first['body']['status']);

        $this->assertSame('ok', $second['status']);
        $this->assertSame(422, $second['http']);
        $this->assertSame(
            'Este jugador ya participa en esta categoría.',
            $second['body']['errors']['player_id'][0]
        );
        $this->assertStringNotContainsString('SQLSTATE', $second['raw']);

        $this->assertSame(1, CategoryEntry::query()->where('category_id', $category->id)->count());
    }

    public function test_concurrent_admin_api_creations_of_one_team_yield_one_entry_and_a_controlled_422(): void
    {
        $category = $this->categoryOfType('doubles');
        $team = $this->doublesTeam($category)['team'];
        $payload = json_encode(['entry_type' => 'team', 'team_id' => $team->id], JSON_THROW_ON_ERROR);

        [$first, $second] = $this->race('api_store_entry', $category->id, $payload, secondBlocksOn: 'for update');

        $this->assertSame(201, $first['http']);
        $this->assertSame(422, $second['http']);
        $this->assertSame('Este equipo ya participa en esta categoría.', $second['body']['errors']['team_id'][0]);
        $this->assertStringNotContainsString('SQLSTATE', $second['raw']);
        $this->assertSame(1, CategoryEntry::query()->where('category_id', $category->id)->count());
    }

    #[DataProvider('identityKinds')]
    public function test_a_writer_that_skips_the_application_checks_is_stopped_by_the_database(string $kind): void
    {
        $category = $this->categoryOfType($kind === 'player' ? 'singles' : 'doubles');
        $identity = $kind === 'player'
            ? ['entry_type' => 'player', 'player_id' => $this->registeredPlayer($category)->id]
            : ['entry_type' => 'team', 'team_id' => $this->doublesTeam($category)['team']->id];

        [$first, $second] = $this->race(
            'raw_insert',
            $category->id,
            json_encode($identity, JSON_THROW_ON_ERROR),
            secondBlocksOn: 'insert into `category_entries`',
        );

        $this->assertSame('ok', $first['status']);
        $this->assertSame('exception', $second['status']);
        $this->assertSame(1062, $second['driver_code']);
        $this->assertTrue($second['translated']);
        $this->assertSame($kind.'_id', $second['field']);
        $this->assertSame(
            $kind === 'player'
                ? CategoryEntryIntegrityException::duplicatePlayer()->getMessage()
                : CategoryEntryIntegrityException::duplicateTeam()->getMessage(),
            $second['message']
        );
        $this->assertSame(1, CategoryEntry::query()->where('category_id', $category->id)->count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function identityKinds(): array
    {
        return ['player identity' => ['player'], 'team identity' => ['team']];
    }

    /**
     * @return array{array<string, mixed>, array<string, mixed>}
     */
    private function race(string $action, int $categoryId, string $payload, string $secondBlocksOn): array
    {
        $directory = sys_get_temp_dir().'/galotxas-entry-race-'.Str::uuid();
        File::makeDirectory($directory, 0700, true);
        $token = User::factory()->admin()->create(['active' => true])->createToken('race')->plainTextToken;

        $first = $this->worker($action, $categoryId, $payload, $token, $directory, 'first', true, true);
        $second = $this->worker($action, $categoryId, $payload, $token, $directory, 'second', false, false);

        try {
            $first->start();
            $this->waitForMarker($directory.'/first.locked');
            touch($directory.'/first.proceed');
            $this->waitForMarker($directory.'/first.acted');

            $second->start();
            $this->waitForMarker($directory.'/second.before_lock');
            $this->waitUntilBlocked($secondBlocksOn);

            touch($directory.'/first.release');
            $first->wait();
            $second->wait();

            $this->assertTrue($first->isSuccessful(), $first->getErrorOutput());
            $this->assertTrue($second->isSuccessful(), $second->getErrorOutput());

            return [$this->decodeOutcome($first), $this->decodeOutcome($second)];
        } finally {
            foreach ([$first, $second] as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
            File::deleteDirectory($directory);
        }
    }

    private function worker(
        string $action,
        int $categoryId,
        string $payload,
        string $token,
        string $directory,
        string $label,
        bool $waitBeforeAction,
        bool $holdAfterAction,
    ): Process {
        return new Process([
            PHP_BINARY,
            base_path('tests/Support/CategoryEntryRaceWorker.php'),
            $action,
            (string) $categoryId,
            $payload,
            $token,
            $directory,
            $label,
            $waitBeforeAction ? '1' : '0',
            $holdAfterAction ? '1' : '0',
        ], base_path(), timeout: 40);
    }

    /**
     * The second process is stuck on a row lock while a statement of that kind from
     * another connection stays in the process list. The assertions never depend on
     * this timing; it only maximises the overlap the test is meant to exercise.
     */
    private function waitUntilBlocked(string $needle): void
    {
        $ownConnection = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
        $deadline = microtime(true) + 15;

        while (microtime(true) < $deadline) {
            if ($this->hasStatement($needle, $ownConnection)) {
                usleep(400_000);

                if ($this->hasStatement($needle, $ownConnection)) {
                    return;
                }
            }

            usleep(50_000);
        }

        $this->fail('El segundo proceso no llegó a bloquearse esperando el bloqueo del primero.');
    }

    private function hasStatement(string $needle, int $ownConnection): bool
    {
        foreach (DB::select('SHOW FULL PROCESSLIST') as $row) {
            if ((int) $row->Id !== $ownConnection && str_contains(strtolower((string) $row->Info), $needle)) {
                return true;
            }
        }

        return false;
    }

    private function waitForMarker(string $path): void
    {
        $deadline = microtime(true) + 20;

        while (! is_file($path)) {
            if (microtime(true) >= $deadline) {
                $this->fail('Timeout esperando la barrera '.$path);
            }

            usleep(10_000);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeOutcome(Process $process): array
    {
        $outcome = json_decode(trim($process->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        $this->assertNotSame('harness_error', $outcome['status'] ?? null, $process->getOutput());

        return $outcome;
    }
}
