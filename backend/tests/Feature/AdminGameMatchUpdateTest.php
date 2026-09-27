<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\Round;
use App\Models\User;
use App\Models\Venue;
use App\Services\MatchResultService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesMatchResultWorkflow;
use Tests\TestCase;

class AdminGameMatchUpdateTest extends TestCase
{
    use CreatesMatchResultWorkflow;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-15 12:00:00', config('app.timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[DataProvider('invalidScheduledDates')]
    public function test_invalid_scheduled_date_is_rejected_without_mutating_the_match(
        string $scheduledDate,
        string $expectedMessage,
    ): void {
        [$match] = $this->createSinglesResultMatch([
            'scheduled_date' => '2026-09-15 18:00:00',
        ]);
        $match->update([
            'status' => 'submitted',
            'home_score' => 10,
            'away_score' => 7,
            'winner_entry_id' => $match->home_entry_id,
        ]);
        $originalVenueId = $match->venue_id;
        $admin = User::factory()->admin()->create();
        $newVenue = Venue::factory()->create();
        $category = $match->round->category;
        $categoryUrl = route('admin.categories.show', $category);

        $this->actingAs($admin)
            ->from($categoryUrl)
            ->patch(route('admin.categories.matches.update', [$category, $match]), [
                'scheduled_date' => $scheduledDate,
                'scheduled_time' => '19:00',
                'venue_id' => $newVenue->id,
                'status' => 'validated',
                'home_score' => 10,
                'away_score' => 6,
            ])
            ->assertRedirect($categoryUrl)
            ->assertSessionHasErrors(['scheduled_date' => $expectedMessage])
            ->assertSessionMissing('success');

        foreach (session('errors')->get('scheduled_date') as $message) {
            $this->assertFalse(
                str_starts_with($message, 'validation.'),
                'A raw validation translation key reached the admin session.'
            );
        }

        $match->refresh();

        $this->assertSame('2026-09-15 18:00:00', $match->scheduled_date->format('Y-m-d H:i:s'));
        $this->assertSame($originalVenueId, $match->venue_id);
        $this->assertSame('submitted', $match->status->value);
        $this->assertSame(10, $match->home_score);
        $this->assertSame(7, $match->away_score);
        $this->assertSame($match->home_entry_id, $match->winner_entry_id);
    }

    public static function invalidScheduledDates(): array
    {
        return [
            'required date' => [
                '',
                'La fecha del partido es obligatoria.',
            ],
            'five-digit year reproduced by the audit' => [
                '20226-01-01',
                'La fecha del partido debe ser una fecha válida con formato AAAA-MM-DD.',
            ],
            'first five-digit year' => [
                '10000-01-01',
                'La fecha del partido debe ser una fecha válida con formato AAAA-MM-DD.',
            ],
            'nonexistent calendar date' => [
                '2026-02-30',
                'La fecha del partido debe ser una fecha válida con formato AAAA-MM-DD.',
            ],
            'noncanonical representation' => [
                '2026-2-03',
                'La fecha del partido debe ser una fecha válida con formato AAAA-MM-DD.',
            ],
            'staging below-minimum example' => [
                '0008-01-01',
                'La fecha del partido no puede ser anterior al 01/01/1000.',
            ],
            'date below the technical range' => [
                '0999-12-31',
                'La fecha del partido no puede ser anterior al 01/01/1000.',
            ],
            'first date after the functional future boundary' => [
                '2028-09-16',
                'La fecha del partido no puede ser posterior a dos años desde hoy.',
            ],
            'staging far-future example' => [
                '2135-01-01',
                'La fecha del partido no puede ser posterior a dos años desde hoy.',
            ],
        ];
    }

    #[DataProvider('validScheduledDates')]
    public function test_valid_scheduled_date_updates_the_match_and_venue(
        string $scheduledDate,
        string $scheduledTime,
    ): void {
        [$match] = $this->createSinglesResultMatch([
            'scheduled_date' => '2026-09-15 18:00:00',
        ]);
        $admin = User::factory()->admin()->create();
        $newVenue = Venue::factory()->create();
        $category = $match->round->category;

        $this->actingAs($admin)
            ->patch(route('admin.categories.matches.update', [$category, $match]), [
                'scheduled_date' => $scheduledDate,
                'scheduled_time' => $scheduledTime,
                'venue_id' => $newVenue->id,
                'status' => 'scheduled',
                'home_score' => null,
                'away_score' => null,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Partido actualizado correctamente.')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'scheduled_date' => $scheduledDate.' '.$scheduledTime.':00',
            'venue_id' => $newVenue->id,
            'status' => 'scheduled',
        ]);
    }

    public static function validScheduledDates(): array
    {
        return [
            'normal date' => ['2026-10-10', '19:00'],
            'historical date from staging acceptance' => ['2001-01-01', '12:00'],
            'lower technical boundary' => ['1000-01-01', '00:00'],
            'functional future boundary' => ['2028-09-15', '23:00'],
        ];
    }

    #[DataProvider('nonCanonicalOccupyingTimes')]
    public function test_admin_rejects_non_canonical_time_for_an_occupying_target(string $scheduledTime): void
    {
        [$match] = $this->createSinglesResultMatch([
            'scheduled_date' => '2026-09-15 18:00:00',
        ]);
        $category = $match->round->category;

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.categories.matches.update', [$category, $match]), [
                'scheduled_date' => '2026-10-10',
                'scheduled_time' => $scheduledTime,
                'venue_id' => Venue::factory()->create()->id,
                'status' => 'scheduled',
                'home_score' => null,
                'away_score' => null,
            ])
            ->assertSessionHas(
                'error',
                'Los partidos que ocupan pista deben comenzar a una hora exacta (HH:00).'
            )
            ->assertSessionMissing('success');

        $this->assertSame('2026-09-15 18:00:00', $match->fresh()->scheduled_date->format('Y-m-d H:i:s'));
    }

    public static function nonCanonicalOccupyingTimes(): array
    {
        return [
            'quarter past' => ['17:15'],
            'half past' => ['17:30'],
            'quarter to' => ['18:45'],
        ];
    }

    public function test_admin_may_preserve_a_non_canonical_time_while_the_match_remains_released(): void
    {
        [$match] = $this->createSinglesResultMatch([
            'scheduled_date' => '2026-09-15 17:30:23',
            'status' => 'postponed',
        ]);
        $category = $match->round->category;

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.categories.matches.update', [$category, $match]), [
                'scheduled_date' => '2026-09-15',
                'scheduled_time' => '17:30',
                'venue_id' => $match->venue_id,
                'status' => 'postponed',
                'home_score' => null,
                'away_score' => null,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame('2026-09-15 17:30:23', $match->fresh()->scheduled_date->format('Y-m-d H:i:s'));
    }

    public function test_admin_may_preserve_an_unchanged_occupying_legacy_slot(): void
    {
        [$match] = $this->createSinglesResultMatch([
            'scheduled_date' => '2026-09-15 17:30:23',
            'status' => 'scheduled',
        ]);
        $category = $match->round->category;

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.categories.matches.update', [$category, $match]), [
                'scheduled_date' => '2026-09-15',
                'scheduled_time' => '17:30',
                'venue_id' => $match->venue_id,
                'status' => 'scheduled',
                'home_score' => null,
                'away_score' => null,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $match->refresh();
        $this->assertSame('2026-09-15 17:30:23', $match->scheduled_date->format('Y-m-d H:i:s'));
        $this->assertSame('scheduled', $match->status->value);
    }

    public function test_admin_may_release_an_occupying_legacy_slot_without_rewriting_it(): void
    {
        [$match] = $this->createSinglesResultMatch([
            'scheduled_date' => '2026-09-15 17:30:23',
            'status' => 'scheduled',
        ]);
        $category = $match->round->category;

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.categories.matches.update', [$category, $match]), [
                'scheduled_date' => '2026-09-15',
                'scheduled_time' => '17:30',
                'venue_id' => $match->venue_id,
                'status' => 'cancelled',
                'home_score' => null,
                'away_score' => null,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $match->refresh();
        $this->assertSame('2026-09-15 17:30:23', $match->scheduled_date->format('Y-m-d H:i:s'));
        $this->assertSame('cancelled', $match->status->value);
    }

    public function test_admin_rejects_reacquiring_an_unchanged_released_legacy_slot(): void
    {
        [$match] = $this->createSinglesResultMatch([
            'scheduled_date' => '2026-09-15 17:30:23',
            'status' => 'postponed',
        ]);
        $category = $match->round->category;

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.categories.matches.update', [$category, $match]), [
                'scheduled_date' => '2026-09-15',
                'scheduled_time' => '17:30',
                'venue_id' => $match->venue_id,
                'status' => 'scheduled',
                'home_score' => null,
                'away_score' => null,
            ])
            ->assertSessionHas(
                'error',
                'Los partidos que ocupan pista deben comenzar a una hora exacta (HH:00).'
            )
            ->assertSessionMissing('success');

        $match->refresh();
        $this->assertSame('2026-09-15 17:30:23', $match->scheduled_date->format('Y-m-d H:i:s'));
        $this->assertSame('postponed', $match->status->value);
    }

    #[DataProvider('legacyOccupancyMoves')]
    public function test_admin_requires_a_canonical_start_when_moving_an_occupying_legacy_slot(
        bool $changeVenue,
        string $scheduledTime,
    ): void {
        [$match] = $this->createSinglesResultMatch([
            'scheduled_date' => '2026-09-15 17:30:23',
            'status' => 'scheduled',
        ]);
        $originalVenueId = $match->venue_id;
        $targetVenueId = $changeVenue ? Venue::factory()->create()->id : $originalVenueId;
        $category = $match->round->category;

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.categories.matches.update', [$category, $match]), [
                'scheduled_date' => '2026-09-15',
                'scheduled_time' => $scheduledTime,
                'venue_id' => $targetVenueId,
                'status' => 'scheduled',
                'home_score' => null,
                'away_score' => null,
            ])
            ->assertSessionHas(
                'error',
                'Los partidos que ocupan pista deben comenzar a una hora exacta (HH:00).'
            )
            ->assertSessionMissing('success');

        $match->refresh();
        $this->assertSame('2026-09-15 17:30:23', $match->scheduled_date->format('Y-m-d H:i:s'));
        $this->assertSame($originalVenueId, $match->venue_id);
    }

    public static function legacyOccupancyMoves(): array
    {
        return [
            'change venue' => [true, '17:30'],
            'change start' => [false, '18:30'],
        ];
    }

    public function test_admin_requires_a_canonical_start_when_scheduling_an_unscheduled_cup_match(): void
    {
        [$leagueMatch] = $this->createSinglesResultMatch();
        $category = $leagueMatch->round->category;
        $cupRound = Round::factory()->create([
            'category_id' => $category->id,
            'name' => 'Semifinales',
            'order' => 1,
            'type' => 'cup',
            'phase' => 'cup',
            'stage' => 'semifinal',
        ]);
        $cupMatch = GameMatch::factory()->create([
            'round_id' => $cupRound->id,
            'venue_id' => null,
            'scheduled_date' => null,
            'status' => 'scheduled',
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.categories.matches.update', [$category, $cupMatch]), [
                'scheduled_date' => '2026-10-10',
                'scheduled_time' => '17:30',
                'venue_id' => Venue::factory()->create()->id,
                'status' => 'scheduled',
                'home_score' => null,
                'away_score' => null,
            ])
            ->assertSessionHas(
                'error',
                'Los partidos que ocupan pista deben comenzar a una hora exacta (HH:00).'
            )
            ->assertSessionMissing('success');

        $cupMatch->refresh();
        $this->assertNull($cupMatch->scheduled_date);
        $this->assertNull($cupMatch->venue_id);
    }

    public function test_service_defense_rejects_non_canonical_seconds_for_an_occupying_target(): void
    {
        [$match] = $this->createSinglesResultMatch([
            'scheduled_date' => '2026-09-15 18:00:00',
        ]);
        $originalVenueId = $match->venue_id;

        try {
            app(MatchResultService::class)->updateFromAdmin(
                $match,
                $match->round->category_id,
                Carbon::parse('2026-10-10 19:00:23'),
                Venue::factory()->create()->id,
                'scheduled',
                null,
                null,
                User::factory()->admin()->create(),
            );
            $this->fail('La defensa del servicio debía rechazar segundos no canónicos.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Los partidos que ocupan pista deben comenzar a una hora exacta (HH:00).',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'scheduled_date' => '2026-09-15 18:00:00',
            'venue_id' => $originalVenueId,
        ]);
    }

    public function test_admin_global_conflict_is_controlled_across_championships_and_does_not_mutate(): void
    {
        [$match] = $this->createSinglesResultMatch([
            'scheduled_date' => '2026-09-15 18:00:00',
        ]);
        $occupiedVenue = Venue::factory()->create();
        [$occupying] = $this->createSinglesResultMatch([
            'venue_id' => $occupiedVenue->id,
            'scheduled_date' => '2026-10-10 19:00:00',
        ]);
        $category = $match->round->category;

        $this->assertNotSame(
            $category->championship_id,
            $occupying->round->category->championship_id
        );

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.categories.matches.update', [$category, $match]), [
                'scheduled_date' => '2026-10-10',
                'scheduled_time' => '19:00',
                'venue_id' => $occupiedVenue->id,
                'status' => 'scheduled',
                'home_score' => null,
                'away_score' => null,
            ])
            ->assertSessionHas(
                'error',
                'La pista seleccionada ya está ocupada en esa fecha y hora por otro partido.'
            )
            ->assertSessionMissing('success');

        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'scheduled_date' => '2026-09-15 18:00:00',
            'venue_id' => $match->venue_id,
            'status' => 'scheduled',
        ]);
    }

    #[DataProvider('canonicalStartsOverlappingLegacyHalfHour')]
    public function test_admin_rejects_a_canonical_start_overlapping_a_legacy_half_hour(
        string $targetStart,
    ): void {
        $venue = Venue::factory()->create();
        $this->createSinglesResultMatch([
            'venue_id' => $venue->id,
            'scheduled_date' => '2026-10-10 17:30:00',
            'status' => 'scheduled',
        ]);
        [$target] = $this->createSinglesResultMatch([
            'scheduled_date' => '2026-09-15 18:00:00',
        ]);

        try {
            app(MatchResultService::class)->updateFromAdmin(
                $target,
                $target->round->category_id,
                Carbon::parse('2026-10-10 '.$targetStart),
                $venue->id,
                'scheduled',
                null,
                null,
                User::factory()->admin()->create(),
            );
            $this->fail('El intervalo legacy debía bloquear el nuevo inicio canónico.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'La pista seleccionada ya está ocupada en esa fecha y hora por otro partido.',
                $exception->getMessage()
            );
        }

        $this->assertSame('2026-09-15 18:00:00', $target->fresh()->scheduled_date->format('Y-m-d H:i:s'));
    }

    public static function canonicalStartsOverlappingLegacyHalfHour(): array
    {
        return [
            'previous canonical hour' => ['17:00:00'],
            'next canonical hour' => ['18:00:00'],
        ];
    }

    public function test_interval_boundaries_other_venues_and_released_legacy_rows_remain_available(): void
    {
        $admin = User::factory()->admin()->create();
        $service = app(MatchResultService::class);
        $legacyVenue = Venue::factory()->create();
        $this->createSinglesResultMatch([
            'venue_id' => $legacyVenue->id,
            'scheduled_date' => '2026-10-10 17:30:00',
            'status' => 'scheduled',
        ]);
        [$afterLegacy] = $this->createSinglesResultMatch();
        $service->updateFromAdmin(
            $afterLegacy,
            $afterLegacy->round->category_id,
            Carbon::parse('2026-10-10 19:00:00'),
            $legacyVenue->id,
            'scheduled',
            null,
            null,
            $admin,
        );

        $canonicalVenue = Venue::factory()->create();
        $this->createSinglesResultMatch([
            'venue_id' => $canonicalVenue->id,
            'scheduled_date' => '2026-10-10 17:00:00',
        ]);
        [$adjacent] = $this->createSinglesResultMatch();
        $service->updateFromAdmin(
            $adjacent,
            $adjacent->round->category_id,
            Carbon::parse('2026-10-10 18:00:00'),
            $canonicalVenue->id,
            'scheduled',
            null,
            null,
            $admin,
        );

        $otherVenue = Venue::factory()->create();
        [$differentVenue] = $this->createSinglesResultMatch();
        $service->updateFromAdmin(
            $differentVenue,
            $differentVenue->round->category_id,
            Carbon::parse('2026-10-10 18:00:00'),
            $otherVenue->id,
            'scheduled',
            null,
            null,
            $admin,
        );

        $releasedVenue = Venue::factory()->create();
        $this->createSinglesResultMatch([
            'venue_id' => $releasedVenue->id,
            'scheduled_date' => '2026-10-10 17:30:00',
            'status' => 'cancelled',
        ]);
        [$releasedLegacy] = $this->createSinglesResultMatch();
        $service->updateFromAdmin(
            $releasedLegacy,
            $releasedLegacy->round->category_id,
            Carbon::parse('2026-10-10 18:00:00'),
            $releasedVenue->id,
            'scheduled',
            null,
            null,
            $admin,
        );

        $this->assertSame('2026-10-10 19:00:00', $afterLegacy->fresh()->scheduled_date->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-10 18:00:00', $adjacent->fresh()->scheduled_date->format('Y-m-d H:i:s'));
        $this->assertSame($otherVenue->id, $differentVenue->fresh()->venue_id);
        $this->assertSame($releasedVenue->id, $releasedLegacy->fresh()->venue_id);
    }

    public function test_admin_self_update_of_the_same_occupying_slot_is_allowed(): void
    {
        [$match] = $this->createSinglesResultMatch([
            'scheduled_date' => '2026-10-10 19:00:00',
        ]);
        $category = $match->round->category;

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.categories.matches.update', [$category, $match]), [
                'scheduled_date' => '2026-10-10',
                'scheduled_time' => '19:00',
                'venue_id' => $match->venue_id,
                'status' => 'scheduled',
                'home_score' => null,
                'away_score' => null,
            ])
            ->assertSessionHas('success')
            ->assertSessionHasNoErrors();
    }

    #[DataProvider('releasedStatuses')]
    public function test_released_rows_do_not_block_admin_occupancy(string $releasedStatus): void
    {
        $venue = Venue::factory()->create();
        [$released] = $this->createSinglesResultMatch([
            'venue_id' => $venue->id,
            'scheduled_date' => '2026-10-10 19:00:00',
            'status' => $releasedStatus,
        ]);
        [$target] = $this->createSinglesResultMatch([
            'scheduled_date' => '2026-09-15 18:00:00',
        ]);
        $category = $target->round->category;

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.categories.matches.update', [$category, $target]), [
                'scheduled_date' => '2026-10-10',
                'scheduled_time' => '19:00',
                'venue_id' => $venue->id,
                'status' => 'scheduled',
                'home_score' => null,
                'away_score' => null,
            ])
            ->assertSessionHas('success');

        $this->assertSame($releasedStatus, $released->fresh()->status->value);
        $this->assertSame($venue->id, $target->fresh()->venue_id);
    }

    public static function releasedStatuses(): array
    {
        return [
            'postponed' => ['postponed'],
            'cancelled' => ['cancelled'],
        ];
    }

    #[DataProvider('releasedStatuses')]
    public function test_admin_released_to_occupying_must_reacquire_the_slot(string $releasedStatus): void
    {
        $venue = Venue::factory()->create();
        [$occupying] = $this->createSinglesResultMatch([
            'venue_id' => $venue->id,
            'scheduled_date' => '2026-10-10 19:00:00',
        ]);
        [$released] = $this->createSinglesResultMatch([
            'venue_id' => $venue->id,
            'scheduled_date' => '2026-10-10 19:00:00',
            'status' => $releasedStatus,
        ]);
        $category = $released->round->category;

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.categories.matches.update', [$category, $released]), [
                'scheduled_date' => '2026-10-10',
                'scheduled_time' => '19:00',
                'venue_id' => $venue->id,
                'status' => 'scheduled',
                'home_score' => null,
                'away_score' => null,
            ])
            ->assertSessionHas('error');

        $this->assertSame($releasedStatus, $released->fresh()->status->value);
        $this->assertSame('scheduled', $occupying->fresh()->status->value);
    }

    public function test_admin_occupying_to_released_frees_the_slot_for_another_match(): void
    {
        $venue = Venue::factory()->create();
        [$owner] = $this->createSinglesResultMatch([
            'venue_id' => $venue->id,
            'scheduled_date' => '2026-10-10 19:00:00',
        ]);
        [$target] = $this->createSinglesResultMatch([
            'scheduled_date' => '2026-09-15 18:00:00',
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('admin.categories.matches.update', [$owner->round->category, $owner]), [
                'scheduled_date' => '2026-10-10',
                'scheduled_time' => '19:00',
                'venue_id' => $venue->id,
                'status' => 'cancelled',
                'home_score' => null,
                'away_score' => null,
            ])
            ->assertSessionHas('success');

        $this->actingAs($admin)
            ->patch(route('admin.categories.matches.update', [$target->round->category, $target]), [
                'scheduled_date' => '2026-10-10',
                'scheduled_time' => '19:00',
                'venue_id' => $venue->id,
                'status' => 'scheduled',
                'home_score' => null,
                'away_score' => null,
            ])
            ->assertSessionHas('success');

        $this->assertSame('cancelled', $owner->fresh()->status->value);
        $this->assertSame($venue->id, $target->fresh()->venue_id);
    }

    public function test_category_match_forms_expose_date_bounds_and_historical_warning_hooks(): void
    {
        [$leagueMatch] = $this->createSinglesResultMatch([
            'scheduled_date' => '2026-09-15 18:00:00',
        ]);
        $category = $leagueMatch->round->category;
        $cupRound = Round::factory()->create([
            'category_id' => $category->id,
            'name' => 'Semifinales',
            'order' => 1,
            'type' => 'cup',
            'phase' => 'cup',
            'stage' => 'semifinal',
        ]);

        GameMatch::factory()->create([
            'round_id' => $cupRound->id,
            'venue_id' => $leagueMatch->venue_id,
            'home_entry_id' => $leagueMatch->home_entry_id,
            'away_entry_id' => $leagueMatch->away_entry_id,
            'scheduled_date' => '2001-01-01 12:30:00',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.categories.show', $category))
            ->assertOk()
            ->assertSee("document.querySelectorAll('[data-admin-match-date]')", false);
        $html = $response->getContent();
        $warning = 'Aviso: la fecha indicada es muy antigua. Comprueba que es correcta si estás registrando un partido o campeonato histórico.';

        $this->assertSame(2, substr_count($html, 'data-admin-match-date="true"'));
        $this->assertSame(2, substr_count($html, 'min="1000-01-01"'));
        $this->assertSame(2, substr_count($html, 'max="2028-09-15"'));
        $this->assertSame(2, substr_count($html, 'data-historical-before="2021-09-15"'));
        $this->assertSame(2, substr_count($html, 'data-admin-historical-date-warning="true"'));
        $this->assertSame(2, substr_count($html, $warning));
    }
}
