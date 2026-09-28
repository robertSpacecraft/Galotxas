<?php

namespace Tests\Feature;

use App\Http\Resources\MatchResource;
use App\Http\Resources\ParticipantMatchResource;
use App\Http\Resources\PublicMatchResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesMatchResultWorkflow;
use Tests\TestCase;

class CompetitionScheduleDateTimeContractTest extends TestCase
{
    use CreatesMatchResultWorkflow;
    use RefreshDatabase;

    public static function scheduleDates(): array
    {
        return [
            'CEST' => ['2026-10-23 17:00:00', '2026-10-23T17:00:00+02:00'],
            'CET after transition' => ['2026-10-30 17:00:00', '2026-10-30T17:00:00+01:00'],
            'CET November' => ['2026-11-06 18:00:00', '2026-11-06T18:00:00+01:00'],
        ];
    }

    #[DataProvider('scheduleDates')]
    public function test_match_api_surfaces_preserve_official_time_and_database_values(string $stored, string $expected): void
    {
        [$match, $home] = $this->createSinglesResultMatch(['scheduled_date' => $stored]);
        $rowBefore = DB::table('game_matches')->where('id', $match->id)->first();
        $attributesBefore = $match->getAttributes();

        foreach ([ParticipantMatchResource::class, PublicMatchResource::class, MatchResource::class] as $resource) {
            $this->assertSame($expected, (new $resource($match))->resolve()['scheduled_date']);
            $this->assertSame($attributesBefore, $match->getAttributes());
            $this->assertFalse($match->isDirty());
        }

        $this->getJson("/api/v1/matches/{$match->id}")
            ->assertOk()->assertJsonPath('data.scheduled_date', $expected);
        $this->getJson("/api/v1/categories/{$match->round->category_id}/schedule")
            ->assertOk()->assertJsonPath('data.0.matches.0.scheduled_date', $expected);

        $this->actingAs($home->user);
        $this->getJson('/api/v1/me/matches')
            ->assertOk()->assertJsonPath('data.0.scheduled_date', $expected);
        $this->getJson('/api/v1/me/calendar')
            ->assertOk()->assertJsonPath('data.0.date', substr($stored, 0, 10))
            ->assertJsonPath('data.0.matches.0.scheduled_date', $expected);
        $this->getJson('/api/v1/me/matches/pending-actions')
            ->assertOk()->assertJsonPath('data.0.match.scheduled_date', $expected);
        $this->getJson("/api/v1/matches/{$match->id}/workflow")
            ->assertOk()->assertJsonPath('data.match.scheduled_date', $expected);
        $this->getJson("/api/v1/matches/{$match->id}/reschedule-workflow")
            ->assertOk()->assertJsonPath('data.match.scheduled_date', $expected);

        $this->actingAs(User::factory()->admin()->create());
        $this->getJson("/api/v1/admin/matches/{$match->id}/conflict")
            ->assertOk()->assertJsonPath('data.match.scheduled_date', $expected)
            ->assertJsonPath('data.match.created_at', $match->created_at->toISOString())
            ->assertJsonPath('data.match.updated_at', $match->updated_at->toISOString());

        $this->assertEquals($rowBefore, DB::table('game_matches')->where('id', $match->id)->first());
        $this->assertSame($stored, DB::table('game_matches')->where('id', $match->id)->value('scheduled_date'));
        $this->assertSame('UTC', config('app.timezone'));
    }

    #[DataProvider('scheduleDates')]
    public function test_reschedule_proposal_serialization_preserves_stored_civil_time(string $stored, string $expected): void
    {
        [$match, $home, $away] = $this->createSinglesResultMatch(['scheduled_date' => $stored]);
        $this->actingAs($home->user)->postJson("/api/v1/matches/{$match->id}/request-reschedule", [
            'scheduled_date' => substr($stored, 0, 10),
            'scheduled_time' => substr($stored, 11, 5),
            'venue_id' => $match->venue_id,
        ])->assertOk()->assertJsonPath('data.requested_scheduled_date', $expected);

        $proposalBefore = DB::table('match_reschedule_requests')->where('game_match_id', $match->id)->first();
        $matchBefore = DB::table('game_matches')->where('id', $match->id)->first();
        $this->assertSame($stored, $proposalBefore->requested_scheduled_date);

        $this->getJson("/api/v1/matches/{$match->id}/reschedule-workflow")
            ->assertOk()->assertJsonPath('data.workflow.my_request.requested_scheduled_date', $expected);
        $this->actingAs($away->user)->getJson("/api/v1/matches/{$match->id}/reschedule-workflow")
            ->assertOk()->assertJsonPath('data.workflow.opposite_request.requested_scheduled_date', $expected);

        $this->assertEquals($proposalBefore, DB::table('match_reschedule_requests')->where('game_match_id', $match->id)->first());
        $this->assertEquals($matchBefore, DB::table('game_matches')->where('id', $match->id)->first());

        $this->postJson("/api/v1/matches/{$match->id}/confirm-reschedule")
            ->assertOk()->assertJsonPath('data.match.scheduled_date', $expected)
            ->assertJsonPath('data.request.requested_scheduled_date', $expected);
        $this->assertSame($stored, DB::table('game_matches')->where('id', $match->id)->value('scheduled_date'));
        $this->assertSame([$stored, $stored], DB::table('match_reschedule_requests')
            ->where('game_match_id', $match->id)->orderBy('id')->pluck('requested_scheduled_date')->all());
    }

    public function test_unscheduled_matches_remain_null_on_every_match_resource(): void
    {
        [$match, $home] = $this->createSinglesResultMatch(['scheduled_date' => null, 'venue_id' => null]);

        foreach ([ParticipantMatchResource::class, PublicMatchResource::class, MatchResource::class] as $resource) {
            $this->assertNull((new $resource($match))->resolve()['scheduled_date']);
        }

        $this->getJson("/api/v1/categories/{$match->round->category_id}/schedule")
            ->assertOk()->assertJsonPath('data.0.matches.0.scheduled_date', null);
        $this->actingAs($home->user)->getJson('/api/v1/me/matches')
            ->assertOk()->assertJsonPath('data.0.scheduled_date', null);
        $this->assertNull(DB::table('game_matches')->where('id', $match->id)->value('scheduled_date'));
    }
}
