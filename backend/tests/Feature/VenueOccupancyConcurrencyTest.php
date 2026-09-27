<?php

namespace Tests\Feature;

use App\Exceptions\VenueOccupancyConflictException;
use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\Championship;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\User;
use App\Models\Venue;
use App\Services\MatchRescheduleRequestService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Concerns\CreatesMatchResultWorkflow;
use Tests\TestCase;

class VenueOccupancyConcurrencyTest extends TestCase
{
    use CreatesMatchResultWorkflow;
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        try {
            $this->truncateTablesForAllConnections();
        } finally {
            parent::tearDown();
        }
    }

    public function test_two_matches_racing_for_one_free_slot_yield_exactly_one_controlled_winner(): void
    {
        [$first] = $this->matchAt('2026-10-01 17:00:00');
        [$second] = $this->matchAt('2026-10-01 18:00:00');
        $venue = Venue::factory()->create();
        $admin = User::factory()->admin()->create();

        [$firstOutcome, $secondOutcome] = $this->blockingRace(
            'admin_update',
            $this->adminPayload($first, $venue, $admin),
            'admin_update',
            $this->adminPayload($second, $venue, $admin),
            'for update',
        );

        $this->assertSame('ok', $firstOutcome['status']);
        $this->assertSame('exception', $secondOutcome['status']);
        $this->assertSame(VenueOccupancyConflictException::class, $secondOutcome['class']);
        $this->assertSame(VenueOccupancyConflictException::MESSAGE, $secondOutcome['message']);
        $this->assertSame(1, GameMatch::query()
            ->where('venue_id', $venue->id)
            ->where('scheduled_date', '2026-10-10 19:00:00')
            ->whereIn('status', ['scheduled', 'submitted', 'under_review', 'validated'])
            ->count());
    }

    public function test_released_to_occupying_race_yields_exactly_one_winner(): void
    {
        [$first] = $this->matchAt('2026-10-01 17:00:00', 'cancelled');
        [$second] = $this->matchAt('2026-10-01 18:00:00', 'postponed');
        $venue = Venue::factory()->create();
        $admin = User::factory()->admin()->create();

        [$firstOutcome, $secondOutcome] = $this->blockingRace(
            'admin_update',
            $this->adminPayload($first, $venue, $admin),
            'admin_update',
            $this->adminPayload($second, $venue, $admin),
            'for update',
        );

        $this->assertSame('ok', $firstOutcome['status']);
        $this->assertSame(VenueOccupancyConflictException::class, $secondOutcome['class']);
        $this->assertSame('postponed', $second->fresh()->status->value);
    }

    public function test_admin_and_participant_confirmation_race_for_one_slot(): void
    {
        [$adminMatch] = $this->matchAt('2026-10-01 17:00:00');
        [$participantMatch, $homePlayer, $awayPlayer] = $this->matchAt('2026-10-01 18:00:00');
        $venue = Venue::factory()->create();
        $admin = User::factory()->admin()->create();

        app(MatchRescheduleRequestService::class)->submitRequest(
            $participantMatch,
            $homePlayer->user,
            '2026-10-10',
            '19:00',
            $venue->id,
        );

        [$adminOutcome, $participantOutcome] = $this->blockingRace(
            'admin_update',
            $this->adminPayload($adminMatch, $venue, $admin),
            'confirm_reschedule',
            [
                'match_id' => $participantMatch->id,
                'user_id' => $awayPlayer->user_id,
            ],
            'for update',
        );

        $this->assertSame('ok', $adminOutcome['status']);
        $this->assertSame(VenueOccupancyConflictException::class, $participantOutcome['class']);
        $this->assertDatabaseHas('match_reschedule_requests', [
            'game_match_id' => $participantMatch->id,
            'status' => 'submitted',
        ]);
    }

    public function test_reversed_origin_destination_batches_serialize_without_deadlock(): void
    {
        $firstVenue = Venue::factory()->create();
        $secondVenue = Venue::factory()->create();

        [$firstOutcome, $secondOutcome] = $this->blockingRace(
            'lock_venues',
            ['venue_ids' => [$firstVenue->id, $secondVenue->id]],
            'lock_venues',
            ['venue_ids' => [$secondVenue->id, $firstVenue->id]],
            'for update',
        );

        $this->assertSame('ok', $firstOutcome['status']);
        $this->assertSame('ok', $secondOutcome['status']);
        $this->assertSame([$firstVenue->id, $secondVenue->id], $firstOutcome['venue_ids']);
        $this->assertSame([$firstVenue->id, $secondVenue->id], $secondOutcome['venue_ids']);
    }

    public function test_two_category_generators_serialize_and_create_no_global_collision(): void
    {
        Venue::factory()->create();
        $firstCategory = $this->categoryWithEntries();
        $secondCategory = $this->categoryWithEntries();

        [$firstOutcome, $secondOutcome] = $this->blockingRace(
            'generate_league',
            ['category_id' => $firstCategory->id],
            'generate_league',
            ['category_id' => $secondCategory->id],
            'for update',
        );

        $this->assertSame('ok', $firstOutcome['status']);
        $this->assertSame('ok', $secondOutcome['status']);
        $occupying = GameMatch::query()
            ->whereIn('status', ['scheduled', 'submitted', 'under_review', 'validated'])
            ->get();
        $keys = $occupying->map(
            fn (GameMatch $match): string => $match->venue_id.'|'.$match->scheduled_date->format('Y-m-d H:i:s')
        );
        $this->assertCount($occupying->count(), $keys->unique());
    }

    public function test_different_venues_do_not_create_a_false_concurrent_conflict(): void
    {
        [$first] = $this->matchAt('2026-10-01 17:00:00');
        [$second] = $this->matchAt('2026-10-01 18:00:00');
        $firstVenue = Venue::factory()->create();
        $secondVenue = Venue::factory()->create();
        $admin = User::factory()->admin()->create();
        $directory = $this->barrierDirectory();
        $firstProcess = $this->worker('admin_update', $this->adminPayload($first, $firstVenue, $admin), $directory, 'first', true);
        $secondProcess = $this->worker('admin_update', $this->adminPayload($second, $secondVenue, $admin), $directory, 'second', false);

        try {
            $firstProcess->start();
            $this->waitForMarker($directory.'/first.acted');
            $secondProcess->run();
            $this->assertTrue($secondProcess->isSuccessful(), $secondProcess->getErrorOutput());
            $this->assertSame('ok', $this->decodeOutcome($secondProcess)['status']);

            touch($directory.'/first.release');
            $firstProcess->wait();
            $this->assertTrue($firstProcess->isSuccessful(), $firstProcess->getErrorOutput());
            $this->assertSame('ok', $this->decodeOutcome($firstProcess)['status']);
        } finally {
            if ($firstProcess->isRunning()) {
                $firstProcess->stop(1);
            }
            File::deleteDirectory($directory);
        }
    }

    public function test_venue_delete_waits_for_an_acquisition_and_then_refuses_deletion(): void
    {
        [$match] = $this->matchAt('2026-10-01 17:00:00');
        $venue = Venue::factory()->create();
        $admin = User::factory()->admin()->create();

        [$writerOutcome, $deleteOutcome] = $this->blockingRace(
            'admin_update',
            $this->adminPayload($match, $venue, $admin),
            'delete_venue',
            ['venue_id' => $venue->id],
            'for update',
        );

        $this->assertSame('ok', $writerOutcome['status']);
        $this->assertSame('ok', $deleteOutcome['status']);
        $this->assertFalse($deleteOutcome['deleted']);
        $this->assertDatabaseHas('venues', ['id' => $venue->id]);
        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'venue_id' => $venue->id,
            'scheduled_date' => '2026-10-10 19:00:00',
        ]);
    }

    public function test_venue_delete_does_not_take_a_reverse_lock_on_a_referencing_match(): void
    {
        [$match] = $this->matchAt('2026-10-01 17:00:00');
        $venueId = (int) $match->venue_id;
        $directory = $this->barrierDirectory();
        $matchLocker = $this->worker(
            'lock_match',
            ['match_id' => $match->id],
            $directory,
            'match_locker',
            true,
        );
        $venueDeleter = $this->worker(
            'delete_venue',
            ['venue_id' => $venueId],
            $directory,
            'venue_deleter',
            false,
        );

        try {
            $matchLocker->start();
            $this->waitForMarker($directory.'/match_locker.acted');
            $venueDeleter->start();
            $this->waitForMarker($directory.'/venue_deleter.acted');
            $venueDeleter->wait();

            $this->assertTrue($venueDeleter->isSuccessful(), $venueDeleter->getErrorOutput());
            $deleteOutcome = $this->decodeOutcome($venueDeleter);
            $this->assertSame('ok', $deleteOutcome['status']);
            $this->assertFalse($deleteOutcome['deleted']);

            touch($directory.'/match_locker.release');
            $matchLocker->wait();
            $this->assertTrue($matchLocker->isSuccessful(), $matchLocker->getErrorOutput());
            $this->assertSame('ok', $this->decodeOutcome($matchLocker)['status']);
            $this->assertDatabaseHas('venues', ['id' => $venueId]);
            $this->assertDatabaseHas('game_matches', [
                'id' => $match->id,
                'venue_id' => $venueId,
            ]);
        } finally {
            foreach ([$matchLocker, $venueDeleter] as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
            File::deleteDirectory($directory);
        }

        [$waitingMatch] = $this->matchAt('2026-10-02 18:00:00');
        $originalVenueId = (int) $waitingMatch->venue_id;
        $destination = Venue::factory()->create();
        $admin = User::factory()->admin()->create();
        $directory = $this->barrierDirectory();
        $venueDeleter = $this->worker(
            'delete_venue_after_barrier',
            ['venue_id' => $destination->id],
            $directory,
            'venue_deleter',
            false,
        );
        $writer = $this->worker(
            'admin_update',
            $this->adminPayload($waitingMatch, $destination, $admin),
            $directory,
            'writer',
            false,
        );

        try {
            $venueDeleter->start();
            $this->waitForMarker($directory.'/venue_deleter.venue_locked');
            $writer->start();
            $this->waitForMarker($directory.'/writer.before_action');
            $this->waitUntilBlocked('for update');
            touch($directory.'/venue_deleter.proceed');
            $venueDeleter->wait();
            $writer->wait();

            $this->assertTrue($venueDeleter->isSuccessful(), $venueDeleter->getErrorOutput());
            $this->assertTrue($writer->isSuccessful(), $writer->getErrorOutput());
            $this->assertTrue($this->decodeOutcome($venueDeleter)['deleted']);
            $writerOutcome = $this->decodeOutcome($writer);
            $this->assertSame('exception', $writerOutcome['status']);
            $this->assertSame(\InvalidArgumentException::class, $writerOutcome['class']);
            $this->assertSame('La pista seleccionada no existe.', $writerOutcome['message']);
            $this->assertDatabaseMissing('venues', ['id' => $destination->id]);
            $this->assertDatabaseHas('game_matches', [
                'id' => $waitingMatch->id,
                'venue_id' => $originalVenueId,
                'scheduled_date' => '2026-10-02 18:00:00',
            ]);
        } finally {
            foreach ([$venueDeleter, $writer] as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
            File::deleteDirectory($directory);
        }
    }

    /** @return array{GameMatch, Player, Player} */
    private function matchAt(string $scheduledDate, string $status = 'scheduled'): array
    {
        return $this->createSinglesResultMatch([
            'scheduled_date' => $scheduledDate,
            'status' => $status,
        ]);
    }

    private function categoryWithEntries(): Category
    {
        $championship = Championship::factory()->create([
            'type' => 'singles',
            'start_date' => '2026-07-03',
        ]);
        $category = Category::factory()->create([
            'championship_id' => $championship->id,
        ]);
        CategoryEntry::factory()->count(4)->playerEntry()->create([
            'category_id' => $category->id,
            'status' => 'approved',
        ]);

        return $category;
    }

    /** @return array<string, int|string> */
    private function adminPayload(GameMatch $match, Venue $venue, User $admin): array
    {
        return [
            'match_id' => $match->id,
            'category_id' => $match->round->category_id,
            'scheduled_date' => '2026-10-10 19:00:00',
            'venue_id' => $venue->id,
            'status' => 'scheduled',
            'admin_id' => $admin->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $firstPayload
     * @param  array<string, mixed>  $secondPayload
     * @return array{array<string, mixed>, array<string, mixed>}
     */
    private function blockingRace(
        string $firstAction,
        array $firstPayload,
        string $secondAction,
        array $secondPayload,
        string $secondBlocksOn,
    ): array {
        $directory = $this->barrierDirectory();
        $first = $this->worker($firstAction, $firstPayload, $directory, 'first', true);
        $second = $this->worker($secondAction, $secondPayload, $directory, 'second', false);

        try {
            $first->start();
            $this->waitForMarker($directory.'/first.acted');
            $second->start();
            $this->waitForMarker($directory.'/second.before_action');
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

    /** @param array<string, mixed> $payload */
    private function worker(
        string $action,
        array $payload,
        string $directory,
        string $label,
        bool $holdAfterAction,
    ): Process {
        return new Process([
            PHP_BINARY,
            base_path('tests/Support/VenueOccupancyRaceWorker.php'),
            $action,
            json_encode($payload, JSON_THROW_ON_ERROR),
            $directory,
            $label,
            $holdAfterAction ? '1' : '0',
        ], base_path(), timeout: 40);
    }

    private function barrierDirectory(): string
    {
        $directory = sys_get_temp_dir().'/galotxas-venue-race-'.Str::uuid();
        File::makeDirectory($directory, 0700, true);

        return $directory;
    }

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

        $this->fail('El segundo proceso no llegó a bloquearse esperando la pista del primero.');
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

    /** @return array<string, mixed> */
    private function decodeOutcome(Process $process): array
    {
        $outcome = json_decode(trim($process->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        $this->assertNotSame('harness_error', $outcome['status'] ?? null, $process->getOutput());

        return $outcome;
    }
}
