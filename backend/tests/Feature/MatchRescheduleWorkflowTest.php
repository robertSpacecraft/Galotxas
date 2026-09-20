<?php

namespace Tests\Feature;

use App\Enums\OfficialResultCompetitionPart;
use App\Exceptions\OfficialResultMutationBlockedException;
use App\Models\CategoryOfficialResult;
use App\Models\GameMatch;
use App\Models\MatchRescheduleRequest;
use App\Models\Player;
use App\Models\User;
use App\Models\Venue;
use App\Services\MatchRescheduleRequestService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesMatchResultWorkflow;
use Tests\TestCase;

class MatchRescheduleWorkflowTest extends TestCase
{
    use CreatesMatchResultWorkflow;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-20 12:00:00', config('app.timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_all_reschedule_endpoints_require_authentication(): void
    {
        [$match] = $this->createMatch();

        $this->getJson($this->workflowUrl($match))->assertUnauthorized();
        $this->postJson($this->requestUrl($match), [])->assertUnauthorized();
        $this->postJson($this->confirmUrl($match), [])->assertUnauthorized();

        $this->assertDatabaseCount('match_reschedule_requests', 0);
    }

    public function test_inactive_participant_is_forbidden_without_mutation(): void
    {
        [$match, $homePlayer] = $this->createMatch();
        $requestedVenue = Venue::factory()->create();
        $homePlayer->user->update(['active' => false]);
        $inactiveUser = $homePlayer->user->fresh();

        $this->actingAs($inactiveUser)
            ->getJson($this->workflowUrl($match))
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'El usuario está inactivo.',
                'data' => null,
            ]);

        $this->actingAs($inactiveUser)
            ->postJson($this->requestUrl($match), $this->validPayload($requestedVenue))
            ->assertForbidden();

        $this->actingAs($inactiveUser)
            ->postJson($this->confirmUrl($match))
            ->assertForbidden();

        $this->assertDatabaseCount('match_reschedule_requests', 0);
        $this->assertSame('2026-09-20 17:00:00', $match->fresh()->scheduled_date->format('Y-m-d H:i:s'));
    }

    public function test_user_without_profile_and_unrelated_player_keep_established_422_semantics(): void
    {
        [$match] = $this->createMatch();
        $requestedVenue = Venue::factory()->create();
        $withoutProfile = User::factory()->create();
        [, $outsider] = $this->createMatch();

        $this->actingAs($withoutProfile)
            ->getJson($this->workflowUrl($match))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El usuario autenticado no tiene un perfil de jugador asociado.')
            ->assertJsonPath('data', null);

        $this->actingAs($withoutProfile)
            ->postJson($this->requestUrl($match), $this->validPayload($requestedVenue))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El usuario autenticado no tiene un perfil de jugador asociado.');

        $this->actingAs($withoutProfile)
            ->postJson($this->confirmUrl($match))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El usuario autenticado no tiene un perfil de jugador asociado.');

        $this->actingAs($outsider->user)
            ->getJson($this->workflowUrl($match))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El jugador no participa en este partido.')
            ->assertJsonPath('data', null);

        $this->actingAs($outsider->user)
            ->postJson($this->requestUrl($match), $this->validPayload($requestedVenue))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El jugador no participa en este partido.');

        $this->actingAs($outsider->user)
            ->postJson($this->confirmUrl($match))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El jugador no participa en este partido.');

        $this->assertDatabaseCount('match_reschedule_requests', 0);
    }

    public function test_initial_workflow_allows_either_singles_participant_to_submit(): void
    {
        [$match, $homePlayer, $awayPlayer] = $this->createMatch();

        $this->actingAs($homePlayer->user)
            ->getJson($this->workflowUrl($match))
            ->assertOk()
            ->assertJsonPath('data.workflow.user_side', 'home')
            ->assertJsonPath('data.workflow.can_submit', true)
            ->assertJsonPath('data.workflow.can_confirm', false)
            ->assertJsonPath('data.workflow.blocked_reason', null);

        $this->actingAs($awayPlayer->user)
            ->getJson($this->workflowUrl($match))
            ->assertOk()
            ->assertJsonPath('data.workflow.user_side', 'away')
            ->assertJsonPath('data.workflow.can_submit', true)
            ->assertJsonPath('data.workflow.can_confirm', false)
            ->assertJsonPath('data.workflow.blocked_reason', null);
    }

    public function test_home_and_away_participants_can_submit_valid_requests_on_independent_matches(): void
    {
        [$homeMatch, $homePlayer] = $this->createMatch();
        $homeVenue = Venue::factory()->create();

        $this->actingAs($homePlayer->user)
            ->postJson($this->requestUrl($homeMatch), $this->validPayload($homeVenue, [
                'comment' => 'Propuesta local',
            ]))
            ->assertOk()
            ->assertJsonPath('message', 'Solicitud de reprogramación enviada correctamente.')
            ->assertJsonPath('data.side', 'home')
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.comment', 'Propuesta local')
            ->assertJsonPath('data.requested_venue.id', $homeVenue->id);

        [$awayMatch, , $awayPlayer] = $this->createMatch();
        $awayVenue = Venue::factory()->create();

        $this->actingAs($awayPlayer->user)
            ->postJson($this->requestUrl($awayMatch), $this->validPayload($awayVenue, [
                'scheduled_date' => '2026-10-11',
                'scheduled_time' => '19:45',
                'comment' => 'Propuesta visitante',
            ]))
            ->assertOk()
            ->assertJsonPath('data.side', 'away')
            ->assertJsonPath('data.status', 'submitted');

        $this->assertDatabaseHas('match_reschedule_requests', [
            'game_match_id' => $homeMatch->id,
            'player_id' => $homePlayer->id,
            'side' => 'home',
            'requested_scheduled_date' => '2026-10-10 18:30:00',
            'requested_venue_id' => $homeVenue->id,
            'status' => 'submitted',
            'comment' => 'Propuesta local',
        ]);
        $this->assertDatabaseHas('match_reschedule_requests', [
            'game_match_id' => $awayMatch->id,
            'player_id' => $awayPlayer->id,
            'side' => 'away',
            'requested_scheduled_date' => '2026-10-11 19:45:00',
            'requested_venue_id' => $awayVenue->id,
            'status' => 'submitted',
            'comment' => 'Propuesta visitante',
        ]);
    }

    public function test_owner_can_update_pending_proposal_and_workflow_keeps_submit_enabled(): void
    {
        [$match, $homePlayer] = $this->createMatch();
        $firstVenue = Venue::factory()->create();
        $secondVenue = Venue::factory()->create();

        $this->actingAs($homePlayer->user)
            ->postJson($this->requestUrl($match), $this->validPayload($firstVenue, [
                'comment' => 'Primera propuesta',
            ]))
            ->assertOk();

        $requestId = MatchRescheduleRequest::query()->sole()->id;

        $this->actingAs($homePlayer->user)
            ->getJson($this->workflowUrl($match))
            ->assertOk()
            ->assertJsonPath('data.workflow.can_submit', true)
            ->assertJsonPath('data.workflow.can_confirm', false)
            ->assertJsonPath('data.workflow.blocked_reason', null)
            ->assertJsonPath('data.workflow.my_request.status', 'submitted');

        $this->actingAs($homePlayer->user)
            ->postJson($this->requestUrl($match), $this->validPayload($secondVenue, [
                'scheduled_date' => '2026-11-02',
                'scheduled_time' => '20:15',
                'comment' => 'Propuesta actualizada',
            ]))
            ->assertOk()
            ->assertJsonPath('data.requested_venue.id', $secondVenue->id)
            ->assertJsonPath('data.comment', 'Propuesta actualizada');

        $this->assertDatabaseCount('match_reschedule_requests', 1);
        $this->assertDatabaseHas('match_reschedule_requests', [
            'id' => $requestId,
            'game_match_id' => $match->id,
            'side' => 'home',
            'requested_scheduled_date' => '2026-11-02 20:15:00',
            'requested_venue_id' => $secondVenue->id,
            'status' => 'submitted',
            'comment' => 'Propuesta actualizada',
        ]);
    }

    public function test_opposite_pending_request_blocks_submission_and_enables_confirmation(): void
    {
        [$match, $homePlayer, $awayPlayer] = $this->createMatch();
        $requestedVenue = Venue::factory()->create();

        $this->actingAs($homePlayer->user)
            ->postJson($this->requestUrl($match), $this->validPayload($requestedVenue))
            ->assertOk();

        $this->actingAs($awayPlayer->user)
            ->getJson($this->workflowUrl($match))
            ->assertOk()
            ->assertJsonPath('data.workflow.can_submit', false)
            ->assertJsonPath('data.workflow.can_confirm', true)
            ->assertJsonPath('data.workflow.blocked_reason', null)
            ->assertJsonPath('data.workflow.opposite_request.side', 'home');

        $this->actingAs($awayPlayer->user)
            ->postJson($this->requestUrl($match), $this->validPayload($requestedVenue, [
                'scheduled_time' => '20:00',
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ya existe una solicitud rival pendiente. Debes confirmarla, no crear una nueva.');

        $this->assertDatabaseCount('match_reschedule_requests', 1);
    }

    public function test_opponent_confirmation_validates_both_requests_and_updates_schedule_atomically(): void
    {
        [$match, $homePlayer, $awayPlayer] = $this->createMatch();
        $requestedVenue = Venue::factory()->create();

        $this->actingAs($homePlayer->user)
            ->postJson($this->requestUrl($match), $this->validPayload($requestedVenue, [
                'scheduled_date' => '2026-11-05',
                'scheduled_time' => '19:15',
                'comment' => 'Cambio acordado',
            ]))
            ->assertOk();

        $this->actingAs($awayPlayer->user)
            ->postJson($this->confirmUrl($match))
            ->assertOk()
            ->assertJsonPath('message', 'Reprogramación confirmada correctamente.')
            ->assertJsonPath('data.match.scheduled_date', '2026-11-05T19:15:00.000000Z')
            ->assertJsonPath('data.match.venue.id', $requestedVenue->id)
            ->assertJsonPath('data.match.status', 'scheduled')
            ->assertJsonPath('data.request.side', 'away')
            ->assertJsonPath('data.request.status', 'validated');

        $this->assertSame(2, MatchRescheduleRequest::query()
            ->where('game_match_id', $match->id)
            ->where('status', 'validated')
            ->count());
        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'scheduled_date' => '2026-11-05 19:15:00',
            'venue_id' => $requestedVenue->id,
            'status' => 'scheduled',
        ]);

        $this->actingAs($homePlayer->user)
            ->getJson($this->workflowUrl($match))
            ->assertOk()
            ->assertJsonPath('data.workflow.can_submit', false)
            ->assertJsonPath('data.workflow.can_confirm', false)
            ->assertJsonPath('data.workflow.blocked_reason', 'already_requested_by_you');
    }

    public function test_doubles_teammate_is_blocked_and_opposite_member_can_confirm(): void
    {
        [$match, $homePlayers, $awayPlayers] = $this->createDoublesResultMatch([
            'scheduled_date' => '2026-09-20 17:00:00',
        ]);
        $requestedVenue = Venue::factory()->create();

        $this->actingAs($homePlayers[1]->user)
            ->postJson($this->requestUrl($match), $this->validPayload($requestedVenue, [
                'comment' => 'Propuesta de la pareja local',
            ]))
            ->assertOk()
            ->assertJsonPath('data.side', 'home');

        $this->actingAs($homePlayers[0]->user)
            ->getJson($this->workflowUrl($match))
            ->assertOk()
            ->assertJsonPath('data.workflow.can_submit', false)
            ->assertJsonPath('data.workflow.can_confirm', false)
            ->assertJsonPath('data.workflow.blocked_reason', 'already_requested_by_teammate')
            ->assertJsonPath('data.workflow.same_side_request_by_teammate.side', 'home');

        $this->actingAs($homePlayers[0]->user)
            ->postJson($this->requestUrl($match), $this->validPayload($requestedVenue))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Tu lado ya ha enviado una solicitud de reprogramación para este partido.');

        $this->actingAs($homePlayers[0]->user)
            ->postJson($this->confirmUrl($match))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Tu lado ya ha enviado una solicitud de reprogramación para este partido.');

        $this->actingAs($awayPlayers[1]->user)
            ->postJson($this->confirmUrl($match))
            ->assertOk()
            ->assertJsonPath('data.request.side', 'away')
            ->assertJsonPath('data.request.status', 'validated');

        $this->assertSame(2, MatchRescheduleRequest::query()->where('game_match_id', $match->id)->count());
    }

    public function test_closed_match_states_reject_submission_and_confirmation_without_mutation(): void
    {
        foreach (['validated', 'cancelled', 'postponed', 'under_review'] as $status) {
            [$match, $homePlayer, $awayPlayer] = $this->createMatch();
            $requestedVenue = Venue::factory()->create();

            app(MatchRescheduleRequestService::class)->submitRequest(
                $match,
                $homePlayer->user,
                '2026-10-10',
                '18:30',
                $requestedVenue->id,
                'Propuesta preservada',
            );

            $match->update(['status' => $status]);
            $originalDate = $match->scheduled_date->format('Y-m-d H:i:s');
            $originalVenueId = $match->venue_id;

            $this->actingAs($homePlayer->user)
                ->postJson($this->requestUrl($match), $this->validPayload($requestedVenue, [
                    'scheduled_date' => '2026-12-01',
                ]))
                ->assertUnprocessable()
                ->assertJsonPath('message', 'Este partido no admite solicitudes de reprogramación.');

            $this->actingAs($awayPlayer->user)
                ->postJson($this->confirmUrl($match))
                ->assertUnprocessable()
                ->assertJsonPath('message', 'Este partido no admite solicitudes de reprogramación.');

            $this->assertSame(1, MatchRescheduleRequest::query()->where('game_match_id', $match->id)->count());
            $this->assertDatabaseHas('match_reschedule_requests', [
                'game_match_id' => $match->id,
                'side' => 'home',
                'status' => 'submitted',
            ]);
            $this->assertDatabaseHas('game_matches', [
                'id' => $match->id,
                'scheduled_date' => $originalDate,
                'venue_id' => $originalVenueId,
                'status' => $status,
            ]);
        }
    }

    public function test_same_championship_exact_occupancy_conflict_rejects_submission(): void
    {
        [$match, $homePlayer] = $this->createMatch();
        $requestedVenue = Venue::factory()->create();
        $this->createOccupyingMatch($match, $requestedVenue, '2026-10-10 18:30:00');

        $this->actingAs($homePlayer->user)
            ->postJson($this->requestUrl($match), $this->validPayload($requestedVenue))
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'La pista seleccionada ya está ocupada en esa fecha y hora para otro partido del mismo campeonato.'
            );

        $this->assertDatabaseCount('match_reschedule_requests', 0);
        $this->assertSame('2026-09-20 17:00:00', $match->fresh()->scheduled_date->format('Y-m-d H:i:s'));
    }

    public function test_occupancy_is_rechecked_on_confirmation_without_partial_mutation(): void
    {
        [$match, $homePlayer, $awayPlayer] = $this->createMatch();
        $requestedVenue = Venue::factory()->create();
        $originalVenueId = $match->venue_id;

        $this->actingAs($homePlayer->user)
            ->postJson($this->requestUrl($match), $this->validPayload($requestedVenue))
            ->assertOk();

        $this->createOccupyingMatch($match, $requestedVenue, '2026-10-10 18:30:00');

        $this->actingAs($awayPlayer->user)
            ->postJson($this->confirmUrl($match))
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'La pista seleccionada ya está ocupada en esa fecha y hora para otro partido del mismo campeonato.'
            );

        $this->assertDatabaseCount('match_reschedule_requests', 1);
        $this->assertDatabaseHas('match_reschedule_requests', [
            'game_match_id' => $match->id,
            'side' => 'home',
            'status' => 'submitted',
        ]);
        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'scheduled_date' => '2026-09-20 17:00:00',
            'venue_id' => $originalVenueId,
            'status' => 'scheduled',
        ]);
    }

    public function test_official_result_blocks_api_confirmation_but_preserves_preliminary_request(): void
    {
        [$match, $homePlayer, $awayPlayer] = $this->createMatch();
        $requestedVenue = Venue::factory()->create();
        $originalVenueId = $match->venue_id;

        $this->actingAs($homePlayer->user)
            ->postJson($this->requestUrl($match), $this->validPayload($requestedVenue))
            ->assertOk();

        CategoryOfficialResult::factory()->create([
            'category_id' => $match->round->category_id,
            'competition_part' => OfficialResultCompetitionPart::LEAGUE->value,
        ]);

        $this->actingAs($awayPlayer->user)
            ->postJson($this->confirmUrl($match))
            ->assertConflict()
            ->assertExactJson([
                'message' => OfficialResultMutationBlockedException::MESSAGE,
                'data' => null,
            ]);

        $this->assertDatabaseCount('match_reschedule_requests', 1);
        $this->assertDatabaseHas('match_reschedule_requests', [
            'game_match_id' => $match->id,
            'side' => 'home',
            'status' => 'submitted',
        ]);
        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'scheduled_date' => '2026-09-20 17:00:00',
            'venue_id' => $originalVenueId,
            'status' => 'scheduled',
        ]);
    }

    #[DataProvider('invalidScheduledDates')]
    public function test_invalid_scheduled_dates_are_rejected_without_mutation(
        ?string $scheduledDate,
        string $expectedMessage,
    ): void {
        [$match, $homePlayer] = $this->createMatch();
        $requestedVenue = Venue::factory()->create();
        $payload = $this->validPayload($requestedVenue);

        if ($scheduledDate === null) {
            unset($payload['scheduled_date']);
        } else {
            $payload['scheduled_date'] = $scheduledDate;
        }

        $response = $this->actingAs($homePlayer->user)
            ->postJson($this->requestUrl($match), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('scheduled_date')
            ->assertJsonPath('errors.scheduled_date.0', $expectedMessage);

        $this->assertStringNotContainsString('validation.', $response->getContent());
        $this->assertNoRescheduleMutation($match);
    }

    public static function invalidScheduledDates(): array
    {
        return [
            'missing' => [null, 'La fecha del partido es obligatoria.'],
            'five-digit year' => ['20226-01-01', 'La fecha del partido debe ser una fecha válida con formato AAAA-MM-DD.'],
            'first five-digit year' => ['10000-01-01', 'La fecha del partido debe ser una fecha válida con formato AAAA-MM-DD.'],
            'slash format' => ['2026/02/03', 'La fecha del partido debe ser una fecha válida con formato AAAA-MM-DD.'],
            'noncanonical month' => ['2026-2-03', 'La fecha del partido debe ser una fecha válida con formato AAAA-MM-DD.'],
            'nonexistent date' => ['2026-02-30', 'La fecha del partido debe ser una fecha válida con formato AAAA-MM-DD.'],
            'staging below-minimum example' => ['0008-01-01', 'La fecha del partido no puede ser anterior al 01/01/1000.'],
            'below technical minimum' => ['0999-12-31', 'La fecha del partido no puede ser anterior al 01/01/1000.'],
            'first day after upper boundary' => ['2028-09-21', 'La fecha del partido no puede ser posterior a dos años desde hoy.'],
            'far future' => ['2135-01-01', 'La fecha del partido no puede ser posterior a dos años desde hoy.'],
        ];
    }

    #[DataProvider('validScheduledDates')]
    public function test_valid_scheduled_date_boundaries_are_accepted(string $scheduledDate): void
    {
        [$match, $homePlayer] = $this->createMatch();
        $requestedVenue = Venue::factory()->create();

        $this->actingAs($homePlayer->user)
            ->postJson($this->requestUrl($match), $this->validPayload($requestedVenue, [
                'scheduled_date' => $scheduledDate,
            ]))
            ->assertOk();

        $this->assertDatabaseHas('match_reschedule_requests', [
            'game_match_id' => $match->id,
            'requested_scheduled_date' => $scheduledDate.' 18:30:00',
            'requested_venue_id' => $requestedVenue->id,
        ]);
    }

    public static function validScheduledDates(): array
    {
        return [
            'technical minimum' => ['1000-01-01'],
            'ordinary historical date' => ['2001-01-01'],
            'dynamic upper boundary' => ['2028-09-20'],
        ];
    }

    #[DataProvider('invalidScheduledTimes')]
    public function test_invalid_scheduled_times_are_rejected_without_mutation(mixed $scheduledTime): void
    {
        [$match, $homePlayer] = $this->createMatch();
        $requestedVenue = Venue::factory()->create();
        $payload = $this->validPayload($requestedVenue);

        if ($scheduledTime === null) {
            unset($payload['scheduled_time']);
        } else {
            $payload['scheduled_time'] = $scheduledTime;
        }

        $response = $this->actingAs($homePlayer->user)
            ->postJson($this->requestUrl($match), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('scheduled_time');

        $this->assertStringNotContainsString('validation.', $response->getContent());
        $this->assertNoRescheduleMutation($match);
    }

    public static function invalidScheduledTimes(): array
    {
        return [
            'missing' => [null],
            'single-digit hour' => ['7:30'],
            'seconds included' => ['18:30:00'],
            'hour overflow' => ['24:00'],
            'minute overflow' => ['18:60'],
            'array' => [['18:30']],
        ];
    }

    #[DataProvider('validScheduledTimes')]
    public function test_valid_scheduled_time_boundaries_are_accepted(string $scheduledTime): void
    {
        [$match, $homePlayer] = $this->createMatch();
        $requestedVenue = Venue::factory()->create();

        $this->actingAs($homePlayer->user)
            ->postJson($this->requestUrl($match), $this->validPayload($requestedVenue, [
                'scheduled_time' => $scheduledTime,
            ]))
            ->assertOk();

        $this->assertDatabaseHas('match_reschedule_requests', [
            'game_match_id' => $match->id,
            'requested_scheduled_date' => '2026-10-10 '.$scheduledTime.':00',
        ]);
    }

    public static function validScheduledTimes(): array
    {
        return [
            'start of day' => ['00:00'],
            'end of day' => ['23:59'],
        ];
    }

    #[DataProvider('invalidVenues')]
    public function test_invalid_venue_is_rejected_without_mutation(mixed $venueId): void
    {
        [$match, $homePlayer] = $this->createMatch();
        $requestedVenue = Venue::factory()->create();
        $payload = $this->validPayload($requestedVenue);

        if ($venueId === null) {
            unset($payload['venue_id']);
        } else {
            $payload['venue_id'] = $venueId;
        }

        $response = $this->actingAs($homePlayer->user)
            ->postJson($this->requestUrl($match), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('venue_id');

        $this->assertStringNotContainsString('validation.', $response->getContent());
        $this->assertNoRescheduleMutation($match);
    }

    public static function invalidVenues(): array
    {
        return [
            'missing' => [null],
            'non-integer' => ['not-a-venue'],
            'nonexistent' => [999999],
        ];
    }

    #[DataProvider('invalidComments')]
    public function test_invalid_comment_is_rejected_without_mutation(mixed $comment): void
    {
        [$match, $homePlayer] = $this->createMatch();
        $requestedVenue = Venue::factory()->create();

        $response = $this->actingAs($homePlayer->user)
            ->postJson($this->requestUrl($match), $this->validPayload($requestedVenue, [
                'comment' => $comment,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('comment');

        $this->assertStringNotContainsString('validation.', $response->getContent());
        $this->assertNoRescheduleMutation($match);
    }

    public static function invalidComments(): array
    {
        return [
            'array' => [['No válido']],
            'too long' => [str_repeat('a', 2001)],
        ];
    }

    /** @return array{GameMatch, Player, Player} */
    private function createMatch(): array
    {
        return $this->createSinglesResultMatch([
            'scheduled_date' => '2026-09-20 17:00:00',
        ]);
    }

    /** @return array<string, mixed> */
    private function validPayload(Venue $venue, array $overrides = []): array
    {
        return array_merge([
            'scheduled_date' => '2026-10-10',
            'scheduled_time' => '18:30',
            'venue_id' => $venue->id,
            'comment' => null,
        ], $overrides);
    }

    private function createOccupyingMatch(GameMatch $match, Venue $venue, string $scheduledDate): GameMatch
    {
        return GameMatch::factory()->create([
            'round_id' => $match->round_id,
            'venue_id' => $venue->id,
            'home_entry_id' => $match->home_entry_id,
            'away_entry_id' => $match->away_entry_id,
            'scheduled_date' => $scheduledDate,
            'status' => 'scheduled',
        ]);
    }

    private function assertNoRescheduleMutation(GameMatch $match): void
    {
        $this->assertDatabaseCount('match_reschedule_requests', 0);
        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'scheduled_date' => '2026-09-20 17:00:00',
            'venue_id' => $match->venue_id,
            'status' => 'scheduled',
        ]);
    }

    private function workflowUrl(GameMatch $match): string
    {
        return "/api/v1/matches/{$match->id}/reschedule-workflow";
    }

    private function requestUrl(GameMatch $match): string
    {
        return "/api/v1/matches/{$match->id}/request-reschedule";
    }

    private function confirmUrl(GameMatch $match): string
    {
        return "/api/v1/matches/{$match->id}/confirm-reschedule";
    }
}
