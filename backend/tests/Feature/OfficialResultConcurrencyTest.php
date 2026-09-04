<?php

namespace Tests\Feature;

use App\Enums\OfficialResultStatus;
use App\Exceptions\LeagueAlreadyOfficialException;
use App\Exceptions\NoCurrentLeagueOfficialResultException;
use App\Exceptions\OfficialResultMutationBlockedException;
use App\Models\CategoryOfficialResult;
use App\Services\OfficializeLeagueResultService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Concerns\CreatesOfficialLeagueFixture;
use Tests\TestCase;

class OfficialResultConcurrencyTest extends TestCase
{
    use CreatesOfficialLeagueFixture;
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        try {
            $this->truncateTablesForAllConnections();
        } finally {
            parent::tearDown();
        }
    }

    public function test_writer_first_then_officialize_snapshots_the_committed_write(): void
    {
        $fixture = $this->createReadySinglesLeague();
        [$writer, $officialize] = $this->race(
            $fixture['category']->id,
            $fixture['matches']->first()->id,
            'writer',
            'officialize',
        );

        $this->assertSame('ok', $writer['status']);
        $this->assertSame('ok', $officialize['status']);
        $result = CategoryOfficialResult::query()->league()->official()->sole();
        $snapshot = $result->matchSnapshots()
            ->where('source_game_match_id', $fixture['matches']->first()->id)
            ->sole();
        $this->assertSame(10, $snapshot->home_score);
        $this->assertSame(8, $snapshot->away_score);
    }

    public function test_officialize_first_blocks_the_waiting_writer(): void
    {
        $fixture = $this->createReadySinglesLeague();
        [$officialize, $writer] = $this->race(
            $fixture['category']->id,
            $fixture['matches']->first()->id,
            'officialize',
            'writer',
        );

        $this->assertSame('ok', $officialize['status']);
        $this->assertException($writer, OfficialResultMutationBlockedException::class);
        $this->assertSame(1, CategoryOfficialResult::query()->league()->official()->count());
    }

    public function test_reopen_first_releases_writer_without_cup_and_cup_keeps_it_blocked(): void
    {
        $withoutCup = $this->createReadySinglesLeague();
        $actor = $this->createActiveAdmin();
        app(OfficializeLeagueResultService::class)->officialize($withoutCup['category'], $actor);
        [$reopen, $writer] = $this->race(
            $withoutCup['category']->id,
            $withoutCup['matches']->first()->id,
            'reopen',
            'writer',
        );
        $this->assertSame('ok', $reopen['status']);
        $this->assertSame('ok', $writer['status']);
        $this->assertSame(OfficialResultStatus::REOPENED, CategoryOfficialResult::query()
            ->where('category_id', $withoutCup['category']->id)
            ->league()
            ->sole()
            ->status);

        $withCup = $this->createReadySinglesLeague();
        app(OfficializeLeagueResultService::class)->officialize($withCup['category'], $actor);
        CategoryOfficialResult::factory()->cup()->create([
            'category_id' => $withCup['category']->id,
        ]);
        [$reopenWithCup, $writerWithCup] = $this->race(
            $withCup['category']->id,
            $withCup['matches']->first()->id,
            'reopen',
            'writer',
        );
        $this->assertSame('ok', $reopenWithCup['status']);
        $this->assertException($writerWithCup, OfficialResultMutationBlockedException::class);
        $this->assertSame(1, CategoryOfficialResult::query()
            ->where('category_id', $withCup['category']->id)
            ->cup()
            ->official()
            ->count());
    }

    public function test_writer_blocked_first_does_not_prevent_waiting_reopen(): void
    {
        $fixture = $this->createReadySinglesLeague();
        app(OfficializeLeagueResultService::class)->officialize(
            $fixture['category'],
            $this->createActiveAdmin(),
        );
        [$writer, $reopen] = $this->race(
            $fixture['category']->id,
            $fixture['matches']->first()->id,
            'writer',
            'reopen',
        );

        $this->assertException($writer, OfficialResultMutationBlockedException::class);
        $this->assertSame('ok', $reopen['status']);
        $this->assertSame(OfficialResultStatus::REOPENED, CategoryOfficialResult::query()
            ->where('category_id', $fixture['category']->id)
            ->league()
            ->sole()
            ->status);
    }

    public function test_two_simultaneous_officialize_calls_produce_one_current_version(): void
    {
        $fixture = $this->createReadySinglesLeague();
        [$first, $second] = $this->race(
            $fixture['category']->id,
            $fixture['matches']->first()->id,
            'officialize',
            'officialize',
        );

        $this->assertSame('ok', $first['status']);
        $this->assertException($second, LeagueAlreadyOfficialException::class);
        $this->assertSame([1], CategoryOfficialResult::query()
            ->where('category_id', $fixture['category']->id)
            ->league()
            ->pluck('version')
            ->all());
    }

    public function test_two_simultaneous_reopen_calls_reopen_exactly_once(): void
    {
        $fixture = $this->createReadySinglesLeague();
        app(OfficializeLeagueResultService::class)->officialize(
            $fixture['category'],
            $this->createActiveAdmin(),
        );
        [$first, $second] = $this->race(
            $fixture['category']->id,
            $fixture['matches']->first()->id,
            'reopen',
            'reopen',
        );

        $this->assertSame('ok', $first['status']);
        $this->assertException($second, NoCurrentLeagueOfficialResultException::class);
        $this->assertSame(OfficialResultStatus::REOPENED, CategoryOfficialResult::query()
            ->where('category_id', $fixture['category']->id)
            ->league()
            ->sole()
            ->status);
    }

    /** @return array{array<string, mixed>, array<string, mixed>} */
    private function race(
        int $categoryId,
        int $matchId,
        string $firstAction,
        string $secondAction,
    ): array {
        $directory = sys_get_temp_dir().'/galotxas-official-race-'.Str::uuid();
        File::makeDirectory($directory, 0700, true);
        $actor = $this->createActiveAdmin();
        $first = $this->worker(
            $firstAction,
            $categoryId,
            $matchId,
            $actor->id,
            $directory,
            'first',
            true,
            true,
        );
        $second = $this->worker(
            $secondAction,
            $categoryId,
            $matchId,
            $actor->id,
            $directory,
            'second',
            false,
            false,
        );

        try {
            $first->start();
            $this->waitForMarker($directory.'/first.locked');
            $second->start();
            $this->waitForMarker($directory.'/second.before_lock');
            touch($directory.'/first.proceed');
            $this->waitForMarker($directory.'/first.acted');
            touch($directory.'/first.release');
            $first->wait();
            $second->wait();

            $this->assertTrue($first->isSuccessful(), $first->getErrorOutput());
            $this->assertTrue($second->isSuccessful(), $second->getErrorOutput());

            return [
                $this->decodeOutcome($first),
                $this->decodeOutcome($second),
            ];
        } finally {
            if ($first->isRunning()) {
                $first->stop(1);
            }
            if ($second->isRunning()) {
                $second->stop(1);
            }
            File::deleteDirectory($directory);
        }
    }

    private function worker(
        string $action,
        int $categoryId,
        int $matchId,
        int $actorId,
        string $directory,
        string $label,
        bool $waitBeforeAction,
        bool $holdAfterAction,
    ): Process {
        return new Process([
            PHP_BINARY,
            base_path('tests/Support/OfficialResultRaceWorker.php'),
            $action,
            (string) $categoryId,
            (string) $matchId,
            (string) $actorId,
            $directory,
            $label,
            $waitBeforeAction ? '1' : '0',
            $holdAfterAction ? '1' : '0',
        ], base_path(), timeout: 20);
    }

    private function waitForMarker(string $path): void
    {
        $deadline = microtime(true) + 15;

        while (! is_file($path)) {
            if (microtime(true) >= $deadline) {
                $this->fail('Timeout esperando la barrera '.$path);
            }

            usleep(10_000);
        }
    }

    /** @return array<string, mixed> */
    private function decodeOutcome(Process $process): array
    {
        $outcome = json_decode(trim($process->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        $this->assertNotSame('harness_error', $outcome['status'] ?? null, $process->getOutput());

        return $outcome;
    }

    /** @param array<string, mixed> $outcome */
    private function assertException(array $outcome, string $class): void
    {
        $this->assertSame('exception', $outcome['status']);
        $this->assertSame($class, $outcome['class']);
    }
}
