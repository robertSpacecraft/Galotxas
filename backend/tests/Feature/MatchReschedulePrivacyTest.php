<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\Player;
use App\Models\User;
use App\Models\Venue;
use App\Services\MatchRescheduleRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesMatchResultWorkflow;
use Tests\TestCase;

class MatchReschedulePrivacyTest extends TestCase
{
    use CreatesMatchResultWorkflow;
    use RefreshDatabase;

    private const HOME_EMAIL = 'reschedule-home@example.test';

    private const AWAY_EMAIL = 'reschedule-away@example.test';

    private const PRIVATE_COMMENT = 'Comentario privado de reprogramación';

    private const MATCH_KEYS = [
        'id',
        'scheduled_date',
        'status',
        'home_score',
        'away_score',
        'home_entry',
        'away_entry',
        'venue',
        'round',
    ];

    private const ENTRY_KEYS = [
        'id',
        'entry_type',
        'public_display_name',
        'player',
        'team',
    ];

    private const REQUEST_KEYS = [
        'side',
        'requested_scheduled_date',
        'status',
        'comment',
        'requested_venue',
    ];

    private const FORBIDDEN_MATCH_PATHS = [
        'result_reports',
        'submitted_by',
        'validated_by',
        'submitted_by_user',
        'validated_by_user',
        'round_id',
        'venue_id',
        'home_entry_id',
        'away_entry_id',
        'winner_entry_id',
        'created_at',
        'updated_at',
        'home_entry.player_id',
        'home_entry.team_id',
        'away_entry.player_id',
        'away_entry.team_id',
        'round.order',
        'round.category.slug',
        'round.category.level',
        'round.category.gender',
        'round.category.status',
        'round.category.championship.slug',
        'round.category.championship.type',
        'round.category.championship.season.status',
    ];

    private const FORBIDDEN_REQUEST_PATHS = [
        'id',
        'game_match_id',
        'user_id',
        'player_id',
        'requested_venue_id',
        'created_at',
        'updated_at',
        'user',
        'player',
    ];

    public function test_workflow_uses_exact_participant_and_minimal_request_allowlists(): void
    {
        [$match, $homePlayer] = $this->createPrivateFixture();
        $requestedVenue = Venue::factory()->create(['name' => 'Pista Solicitada']);

        app(MatchRescheduleRequestService::class)->submitRequest(
            $match,
            $homePlayer->user,
            '2026-10-10',
            '18:30',
            $requestedVenue->id,
            self::PRIVATE_COMMENT,
        );
        $match->update([
            'status' => 'submitted',
            'home_score' => 10,
            'away_score' => 7,
            'submitted_by' => $homePlayer->user_id,
        ]);

        $response = $this->actingAs($homePlayer->user)
            ->getJson($this->workflowUrl($match))
            ->assertOk()
            ->assertJsonPath('data.match.home_score', null)
            ->assertJsonPath('data.match.away_score', null)
            ->assertJsonPath('data.workflow.my_request.comment', self::PRIVATE_COMMENT);

        $this->assertExactKeys($response, 'data.match', self::MATCH_KEYS);
        $this->assertExactKeys($response, 'data.match.home_entry', self::ENTRY_KEYS);
        $this->assertExactKeys($response, 'data.workflow.my_request', self::REQUEST_KEYS);
        $this->assertExactKeys($response, 'data.workflow.my_request.requested_venue', ['id', 'name']);
        $this->assertForbiddenMatchPaths($response, 'data.match');
        $this->assertForbiddenRequestPaths($response, 'data.workflow.my_request');
        $this->assertNoEmailOrActorLeak($response);
    }

    public function test_submit_response_has_only_minimal_reschedule_request_fields(): void
    {
        [$match, $homePlayer] = $this->createPrivateFixture();
        $requestedVenue = Venue::factory()->create(['name' => 'Pista Nueva']);

        $response = $this->actingAs($homePlayer->user)
            ->postJson($this->requestUrl($match), $this->payload($requestedVenue))
            ->assertOk()
            ->assertJsonPath('data.side', 'home')
            ->assertJsonPath('data.comment', self::PRIVATE_COMMENT)
            ->assertJsonPath('data.requested_venue.id', $requestedVenue->id)
            ->assertJsonPath('data.requested_venue.name', 'Pista Nueva');

        $this->assertExactKeys($response, 'data', self::REQUEST_KEYS);
        $this->assertExactKeys($response, 'data.requested_venue', ['id', 'name']);
        $this->assertForbiddenRequestPaths($response, 'data');
        $this->assertNoEmailOrActorLeak($response);
    }

    public function test_confirm_response_uses_minimal_match_and_request_contracts(): void
    {
        [$match, $homePlayer, $awayPlayer] = $this->createPrivateFixture();
        $requestedVenue = Venue::factory()->create(['name' => 'Pista Confirmada']);

        app(MatchRescheduleRequestService::class)->submitRequest(
            $match,
            $homePlayer->user,
            '2026-10-12',
            '20:00',
            $requestedVenue->id,
            self::PRIVATE_COMMENT,
        );

        $response = $this->actingAs($awayPlayer->user)
            ->postJson($this->confirmUrl($match))
            ->assertOk()
            ->assertJsonPath('data.match.venue.id', $requestedVenue->id)
            ->assertJsonPath('data.request.side', 'away')
            ->assertJsonPath('data.request.status', 'validated');

        $this->assertExactKeys($response, 'data.match', self::MATCH_KEYS);
        $this->assertExactKeys($response, 'data.request', self::REQUEST_KEYS);
        $this->assertExactKeys($response, 'data.request.requested_venue', ['id', 'name']);
        $this->assertForbiddenMatchPaths($response, 'data.match');
        $this->assertForbiddenRequestPaths($response, 'data.request');
        $this->assertNoEmailOrActorLeak($response);
    }

    public function test_doubles_keep_allowed_participant_identity_without_requester_identity(): void
    {
        [$match, $homePlayers] = $this->createDoublesResultMatch([
            'scheduled_date' => '2026-09-20 17:00:00',
        ]);
        $requestedVenue = Venue::factory()->create();

        $homePlayers[0]->user->update(['email' => self::HOME_EMAIL]);
        $homePlayers[1]->user->update(['email' => 'reschedule-teammate@example.test']);

        app(MatchRescheduleRequestService::class)->submitRequest(
            $match,
            $homePlayers[0]->user->fresh(),
            '2026-10-10',
            '18:30',
            $requestedVenue->id,
            self::PRIVATE_COMMENT,
        );

        $response = $this->actingAs($homePlayers[1]->user->fresh())
            ->getJson($this->workflowUrl($match))
            ->assertOk()
            ->assertJsonPath('data.match.home_entry.entry_type', 'team')
            ->assertJsonCount(2, 'data.match.home_entry.team.players')
            ->assertJsonPath('data.workflow.same_side_request_by_teammate.side', 'home');

        $this->assertExactKeys(
            $response,
            'data.workflow.same_side_request_by_teammate',
            self::REQUEST_KEYS,
        );
        $response
            ->assertJsonMissingPath('data.workflow.same_side_request_by_teammate.user')
            ->assertJsonMissingPath('data.workflow.same_side_request_by_teammate.player');
        $this->assertNoEmailOrActorLeak($response);
    }

    public function test_rejected_users_receive_no_private_request_or_match_payload(): void
    {
        [$match, $homePlayer] = $this->createPrivateFixture();
        $requestedVenue = Venue::factory()->create();
        $withoutProfile = User::factory()->create(['email' => 'without-profile@example.test']);
        $outsider = $this->createResultPlayer();

        app(MatchRescheduleRequestService::class)->submitRequest(
            $match,
            $homePlayer->user,
            '2026-10-10',
            '18:30',
            $requestedVenue->id,
            self::PRIVATE_COMMENT,
        );

        $responses = [
            $this->actingAs($withoutProfile)->getJson($this->workflowUrl($match)),
            $this->actingAs($withoutProfile)->postJson($this->requestUrl($match), $this->payload($requestedVenue)),
            $this->actingAs($withoutProfile)->postJson($this->confirmUrl($match)),
            $this->actingAs($outsider->user)->getJson($this->workflowUrl($match)),
            $this->actingAs($outsider->user)->postJson($this->requestUrl($match), $this->payload($requestedVenue)),
            $this->actingAs($outsider->user)->postJson($this->confirmUrl($match)),
        ];

        foreach ($responses as $response) {
            $response
                ->assertUnprocessable()
                ->assertJsonPath('data', null)
                ->assertJsonMissingPath('data.match')
                ->assertJsonMissingPath('data.workflow');

            $content = $response->getContent();
            $this->assertStringNotContainsString(self::PRIVATE_COMMENT, $content);
            $this->assertStringNotContainsString(self::HOME_EMAIL, $content);
            $this->assertStringNotContainsString(self::AWAY_EMAIL, $content);
        }
    }

    private function assertExactKeys(TestResponse $response, string $path, array $expected): void
    {
        $this->assertEqualsCanonicalizing($expected, array_keys($response->json($path)));
    }

    private function assertForbiddenMatchPaths(TestResponse $response, string $prefix): void
    {
        foreach (self::FORBIDDEN_MATCH_PATHS as $path) {
            $response->assertJsonMissingPath($prefix.'.'.$path);
        }
    }

    private function assertForbiddenRequestPaths(TestResponse $response, string $prefix): void
    {
        foreach (self::FORBIDDEN_REQUEST_PATHS as $path) {
            $response->assertJsonMissingPath($prefix.'.'.$path);
        }
    }

    private function assertNoEmailOrActorLeak(TestResponse $response): void
    {
        $content = $response->getContent();

        $this->assertStringNotContainsString(self::HOME_EMAIL, $content);
        $this->assertStringNotContainsString(self::AWAY_EMAIL, $content);
        $this->assertStringNotContainsString('@example.test', $content);
    }

    /** @return array{GameMatch, Player, Player} */
    private function createPrivateFixture(): array
    {
        [$match, $homePlayer, $awayPlayer] = $this->createSinglesResultMatch([
            'scheduled_date' => '2026-09-20 17:00:00',
        ]);

        $homePlayer->user->update([
            'email' => self::HOME_EMAIL,
            'name' => 'Jugador',
            'lastname' => 'Local',
        ]);
        $awayPlayer->user->update([
            'email' => self::AWAY_EMAIL,
            'name' => 'Jugador',
            'lastname' => 'Rival',
        ]);

        return [
            $match,
            $homePlayer->load('user'),
            $awayPlayer->load('user'),
        ];
    }

    /** @return array<string, mixed> */
    private function payload(Venue $venue): array
    {
        return [
            'scheduled_date' => '2026-10-10',
            'scheduled_time' => '18:30',
            'venue_id' => $venue->id,
            'comment' => self::PRIVATE_COMMENT,
        ];
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
