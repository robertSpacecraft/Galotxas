<?php

namespace Tests\Feature;

use App\Enums\OfficialResultMutationImpact;
use App\Enums\OfficialResultStatus;
use App\Exceptions\CupAlreadyOfficialException;
use App\Exceptions\CupOfficializationNotReadyException;
use App\Exceptions\NoCurrentCupOfficialResultException;
use App\Exceptions\OfficialResultMutationBlockedException;
use App\Models\CategoryOfficialResult;
use App\Services\MatchResultService;
use App\Services\OfficializeCupResultService;
use App\Services\OfficializeLeagueResultService;
use App\Services\OfficialResultMutationGuard;
use App\Services\ReopenCupResultService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Concerns\CreatesOfficialCupFixture;
use Tests\TestCase;

class CupOfficialResultConcurrencyTest extends TestCase
{
    use CreatesOfficialCupFixture;
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        try {
            $this->truncateTablesForAllConnections();
        } finally {
            parent::tearDown();
        }
    }

    public function test_cup_writer_first_is_snapshotted_and_officialize_first_blocks_writer(): void
    {
        $writerFirst = $this->createReadySinglesCup();
        [$writer, $officialize] = $this->race(
            $writerFirst['category']->id,
            $writerFirst['finalMatch']->id,
            'cup_writer',
            'officialize_cup',
        );
        $this->assertSame('ok', $writer['status']);
        $this->assertSame('ok', $officialize['status']);
        $snapshot = CategoryOfficialResult::query()->cup()->official()->sole()
            ->matchSnapshots()
            ->where('source_game_match_id', $writerFirst['finalMatch']->id)
            ->sole();
        $this->assertSame(10, $snapshot->home_score);
        $this->assertSame(8, $snapshot->away_score);

        $officializeFirst = $this->createReadySinglesCup();
        [$officialize, $writer] = $this->race(
            $officializeFirst['category']->id,
            $officializeFirst['finalMatch']->id,
            'officialize_cup',
            'cup_writer',
        );
        $this->assertSame('ok', $officialize['status']);
        $this->assertException($writer, OfficialResultMutationBlockedException::class);
    }

    public function test_reopen_first_releases_cup_writer_and_blocked_writer_does_not_prevent_reopen(): void
    {
        $reopenFirst = $this->createReadySinglesCup();
        app(OfficializeCupResultService::class)->officialize(
            $reopenFirst['category'],
            $this->createActiveAdmin(),
        );
        [$reopen, $writer] = $this->race(
            $reopenFirst['category']->id,
            $reopenFirst['finalMatch']->id,
            'reopen_cup',
            'cup_writer',
        );
        $this->assertSame('ok', $reopen['status']);
        $this->assertSame('ok', $writer['status']);

        $writerFirst = $this->createReadySinglesCup();
        app(OfficializeCupResultService::class)->officialize(
            $writerFirst['category'],
            $this->createActiveAdmin(),
        );
        [$writer, $reopen] = $this->race(
            $writerFirst['category']->id,
            $writerFirst['finalMatch']->id,
            'cup_writer',
            'reopen_cup',
        );
        $this->assertException($writer, OfficialResultMutationBlockedException::class);
        $this->assertSame('ok', $reopen['status']);
    }

    public function test_simultaneous_officialize_and_reopen_each_have_one_winner(): void
    {
        $officializeFixture = $this->createReadySinglesCup();
        [$first, $second] = $this->race(
            $officializeFixture['category']->id,
            $officializeFixture['finalMatch']->id,
            'officialize_cup',
            'officialize_cup',
        );
        $this->assertSame('ok', $first['status']);
        $this->assertException($second, CupAlreadyOfficialException::class);
        $this->assertSame([1], CategoryOfficialResult::query()
            ->where('category_id', $officializeFixture['category']->id)
            ->cup()
            ->pluck('version')
            ->all());

        $reopenFixture = $this->createReadySinglesCup();
        app(OfficializeCupResultService::class)->officialize(
            $reopenFixture['category'],
            $this->createActiveAdmin(),
        );
        [$first, $second] = $this->race(
            $reopenFixture['category']->id,
            $reopenFixture['finalMatch']->id,
            'reopen_cup',
            'reopen_cup',
        );
        $this->assertSame('ok', $first['status']);
        $this->assertException($second, NoCurrentCupOfficialResultException::class);
    }

    public function test_reopening_league_does_not_release_its_writer_while_cup_remains_official(): void
    {
        $fixture = $this->createReadySinglesCup();
        $actor = $this->createActiveAdmin();
        app(OfficializeLeagueResultService::class)->officialize($fixture['category'], $actor);
        app(OfficializeCupResultService::class)->officialize($fixture['category'], $actor);

        [$reopen, $writer] = $this->race(
            $fixture['category']->id,
            $fixture['matches']->first()->id,
            'reopen',
            'writer',
        );
        $this->assertSame('ok', $reopen['status']);
        $this->assertException($writer, OfficialResultMutationBlockedException::class);
        $this->assertSame(1, CategoryOfficialResult::query()
            ->where('category_id', $fixture['category']->id)
            ->cup()
            ->official()
            ->count());
    }

    public function test_reopening_cup_releases_cup_writer_but_league_still_blocks_its_sources(): void
    {
        $fixture = $this->createReadySinglesCup();
        $actor = $this->createActiveAdmin();
        app(OfficializeLeagueResultService::class)->officialize($fixture['category'], $actor);
        app(OfficializeCupResultService::class)->officialize($fixture['category'], $actor);

        [$reopen, $cupWriter] = $this->race(
            $fixture['category']->id,
            $fixture['finalMatch']->id,
            'reopen_cup',
            'cup_writer',
        );
        $this->assertSame('ok', $reopen['status']);
        $this->assertSame('ok', $cupWriter['status']);
        $this->assertSame(OfficialResultStatus::OFFICIAL, CategoryOfficialResult::query()
            ->where('category_id', $fixture['category']->id)
            ->league()
            ->sole()
            ->status);

        $this->expectException(OfficialResultMutationBlockedException::class);
        app(MatchResultService::class)->validateExistingResult(
            $fixture['matches']->first(),
            $actor,
        );
    }

    public function test_participants_remain_blocked_by_league_after_reopening_cup(): void
    {
        $fixture = $this->createReadySinglesCup();
        $actor = $this->createActiveAdmin();
        app(OfficializeLeagueResultService::class)->officialize($fixture['category'], $actor);
        app(OfficializeCupResultService::class)->officialize($fixture['category'], $actor);
        app(ReopenCupResultService::class)->reopen(
            $fixture['category'],
            $actor,
            'Mantener Liga',
        );

        $this->expectException(OfficialResultMutationBlockedException::class);
        DB::transaction(fn () => app(OfficialResultMutationGuard::class)->lockAndGuard(
            $fixture['category'],
            OfficialResultMutationImpact::PARTICIPANTS,
        ));
    }

    public function test_league_writer_first_changes_seed_and_waiting_officialize_rejects_stale_bracket(): void
    {
        $fixture = $this->createReadySinglesCup();
        $thirdSeedId = (int) $fixture['seed'][2]->id;
        $fourthSeedId = (int) $fixture['seed'][3]->id;
        $rankingMatch = $fixture['matches']->first(function ($match) use ($thirdSeedId, $fourthSeedId): bool {
            $participantIds = [(int) $match->home_entry_id, (int) $match->away_entry_id];
            sort($participantIds);

            $seedIds = [$thirdSeedId, $fourthSeedId];
            sort($seedIds);

            return $participantIds === $seedIds;
        });
        $this->assertNotNull($rankingMatch);

        [$writer, $officialize] = $this->race(
            $fixture['category']->id,
            $rankingMatch->id,
            'seed_writer',
            'officialize_cup',
        );
        $this->assertSame('ok', $writer['status']);
        $this->assertException($officialize, CupOfficializationNotReadyException::class);
        $this->assertSame(0, CategoryOfficialResult::query()
            ->where('category_id', $fixture['category']->id)
            ->cup()
            ->count());
    }

    public function test_officialize_first_blocks_a_waiting_league_seed_writer(): void
    {
        $fixture = $this->createReadySinglesCup();
        [$officialize, $writer] = $this->race(
            $fixture['category']->id,
            $fixture['matches']->first()->id,
            'officialize_cup',
            'writer',
        );
        $this->assertSame('ok', $officialize['status']);
        $this->assertException($writer, OfficialResultMutationBlockedException::class);
    }

    /** @return array{array<string, mixed>, array<string, mixed>} */
    private function race(
        int $categoryId,
        int $matchId,
        string $firstAction,
        string $secondAction,
    ): array {
        $directory = sys_get_temp_dir().'/galotxas-cup-official-race-'.Str::uuid();
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

            return [$this->decodeOutcome($first), $this->decodeOutcome($second)];
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
