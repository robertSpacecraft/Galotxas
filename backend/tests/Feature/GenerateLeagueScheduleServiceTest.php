<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\CategoryOfficialResult;
use App\Models\Championship;
use App\Models\GameMatch;
use App\Models\Round;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use App\Services\ChampionshipCalendarWindow;
use App\Services\GenerateLeagueScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class GenerateLeagueScheduleServiceTest extends TestCase
{
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        try {
            $this->truncateDatabaseTables();
        } finally {
            parent::tearDown();
        }
    }

    public function test_global_round_ordinals_byes_approved_entries_and_safe_replacement(): void
    {
        $this->courts();
        $champ = $this->championship();
        $even = $this->category($champ, 4);
        $odd = $this->category($champ, 5);
        CategoryEntry::factory()->playerEntry()->create(['category_id' => $even->id, 'status' => 'pending']);

        $this->generate($champ);
        $this->assertSame(3, $even->rounds()->count());
        $this->assertSame(5, $odd->rounds()->count());
        $this->assertSame(16, GameMatch::count());
        $pairs = [];
        foreach (GameMatch::with('round', 'venue')->get() as $match) {
            $friday = Carbon::parse('2026-07-03')->addWeeks($match->round->order - 1);
            $this->assertContains($match->scheduled_date->toDateString(), [$friday->toDateString(), $friday->copy()->addDay()->toDateString()]);
            $this->assertContains($match->scheduled_date->format('H:i:s'), ['17:00:00', '18:00:00', '19:00:00', '20:00:00']);
            $this->assertNotContains($match->venue->court_number, [1, 6]);
            $pair = [$match->home_entry_id, $match->away_entry_id];
            sort($pair);
            $pairs[] = implode(':', $pair);
        }
        $this->assertCount(16, array_unique($pairs));
        $oddAppearances = $this->leagueMatches($odd)->flatMap(fn ($m) => [$m->home_entry_id, $m->away_entry_id])->countBy();
        $this->assertSame([4, 4, 4, 4, 4], $oddAppearances->sort()->values()->all());
        $before = GameMatch::orderBy('id')->get();
        $plan = $before->map(fn ($m) => [$m->home_entry_id, $m->away_entry_id, $m->venue_id, (string) $m->scheduled_date])->all();
        $this->generate($champ);
        $this->assertSame(0, GameMatch::whereKey($before->modelKeys())->count());
        $this->assertSame($plan, GameMatch::orderBy('id')->get()->map(fn ($m) => [$m->home_entry_id, $m->away_entry_id, $m->venue_id, (string) $m->scheduled_date])->all());
    }

    public function test_non_friday_start_uses_next_friday_and_reserves_final_two_anchors(): void
    {
        $this->courts([2]);
        $champ = $this->championship(['start_date' => '2026-07-04', 'end_date' => '2026-07-24']);
        $this->category($champ, 2);
        $window = app(ChampionshipCalendarWindow::class)->weekends($champ, 1);
        $this->assertSame(['2026-07-17', '2026-07-24'], array_map(fn ($d) => $d->toDateString(), $window['cup']));
        $this->generate($champ);
        $this->assertSame('2026-07-10 17:00:00', (string) GameMatch::sole()->scheduled_date);
        $this->assertSame(0, Round::where('type', 'cup')->count());
    }

    public function test_ten_entries_fit_nine_rounds_in_exactly_eleven_friday_anchors(): void
    {
        $this->courts();
        $champ = $this->championship();
        $this->category($champ, 10);
        $this->generate($champ);
        $this->assertSame(9, Round::count());
        $this->assertSame(45, GameMatch::count());
        $this->assertTrue(GameMatch::get()->every(fn ($m) => $m->scheduled_date->lt('2026-09-04')));
    }

    public function test_all_eight_weekly_hours_including_saturday_twenty_are_used_when_required(): void
    {
        $this->courts([2]);
        $champ = $this->championship();
        $this->category($champ, 8);
        $this->category($champ, 8);
        $this->generate($champ);
        $dates = GameMatch::whereHas('round', fn ($q) => $q->where('order', 1))->orderBy('scheduled_date')->pluck('scheduled_date')->map(fn ($d) => (string) $d)->all();
        $this->assertSame([
            '2026-07-03 17:00:00', '2026-07-03 18:00:00', '2026-07-03 19:00:00', '2026-07-03 20:00:00',
            '2026-07-04 17:00:00', '2026-07-04 18:00:00', '2026-07-04 19:00:00', '2026-07-04 20:00:00',
        ], $dates);
    }

    #[DataProvider('invalidPrerequisites')]
    public function test_prerequisites_fail_without_partial_calendar(array $champChanges, int $entries, array $categoryChanges, string $message): void
    {
        $this->courts();
        $champ = $this->championship($champChanges);
        $this->category($champ, $entries, $categoryChanges);
        $this->fails($champ, $message);
        $this->assertSame(0, Round::count());
        $this->assertSame(0, GameMatch::count());
    }

    public static function invalidPrerequisites(): array
    {
        return [
            'short window' => [['end_date' => '2026-07-10'], 2, [], 'dos semanas'],
            'null dates' => [['start_date' => null], 2, [], 'fechas de inicio y fin'],
            'missing end' => [['end_date' => null], 2, [], 'fechas de inicio y fin'],
            'too many' => [[], 11, [], 'entre 2 y 10'],
            'too few' => [[], 1, [], 'entre 2 y 10'],
            'legacy category' => [[], 2, ['age_group' => null], 'grupo de edad'],
        ];
    }

    public function test_singles_first_male_uses_structural_courts_four_five_only(): void
    {
        Venue::factory()->create(['court_number' => null, 'name' => 'Pista 4']);
        Venue::factory()->create(['court_number' => 99, 'name' => 'Pista 5']);
        $this->courts([6, 5, 4, 3, 2, 1]);
        $champ = $this->championship();
        $first = $this->category($champ, 10, ['level' => 1, 'gender' => 'male', 'name' => 'Nada que ver']);
        $youth = $this->category($champ, 4, ['level' => 1, 'gender' => 'male', 'age_group' => 'youth', 'name' => 'Primera masculina']);
        $this->generate($champ);
        $this->assertTrue($this->leagueMatches($first)->every(fn ($m) => in_array($m->venue->court_number, [4, 5], true)));
        $this->assertTrue($this->leagueMatches($youth)->every(fn ($m) => in_array($m->venue->court_number, [2, 3, 4, 5], true)));
        $this->assertTrue(GameMatch::with('venue')->get()->every(fn ($m) => $m->venue->court_number !== 1));
    }

    public function test_doubles_first_male_uses_exclusive_court_one(): void
    {
        $this->courts();
        $champ = $this->championship(['type' => 'doubles']);
        $first = $this->category($champ, 4, ['level' => 1, 'gender' => 'male']);
        $other = $this->category($champ, 4);
        $this->generate($champ);
        $this->assertTrue($this->leagueMatches($first)->every(fn ($m) => $m->venue->court_number === 1));
        $this->assertTrue($this->leagueMatches($other)->every(fn ($m) => $m->venue->court_number !== 1));
        $this->assertSame(12, GameMatch::count());
    }

    public function test_missing_hard_court_does_not_fall_back_to_ids_or_names(): void
    {
        $this->courts([2, 6]);
        Venue::factory()->create(['court_number' => null, 'name' => 'Pista 1']);
        $champ = $this->championship(['type' => 'doubles']);
        $this->category($champ, 2, ['level' => 1, 'gender' => 'male']);
        $this->fails($champ, 'Faltan pistas requeridas');
        $this->assertSame(0, Round::count());
    }

    public function test_legacy_external_interval_blocks_both_adjacent_canonical_candidates(): void
    {
        $venues = $this->courts([2]);
        $external = GameMatch::factory()->create([
            'venue_id' => $venues[2]->id, 'scheduled_date' => '2026-07-03 17:30:00', 'status' => 'scheduled',
        ]);
        $champ = $this->championship();
        $category = $this->category($champ, 4);
        $this->generate($champ);
        $this->assertSame(['2026-07-03 19:00:00', '2026-07-03 20:00:00'],
            $this->leagueMatches($category)->filter(fn ($m) => $m->round->order === 1)->pluck('scheduled_date')->map(fn ($d) => (string) $d)->values()->all());
        $this->assertNotSame($champ->season_id, $external->round->category->championship->season_id);
    }

    public function test_overflow_sends_youth_first_even_when_youth_was_created_first(): void
    {
        $this->courts([2, 6]);
        $champ = $this->championship();
        $youth = $this->category($champ, 10, ['age_group' => 'youth']);
        $open = $this->category($champ, 10, ['level' => 6]);
        $this->generate($champ);
        $this->assertSame(18, $this->leagueMatches($youth)->filter(fn ($m) => $m->venue->court_number === 6)->count());
        $this->assertSame(0, $this->leagueMatches($open)->filter(fn ($m) => $m->venue->court_number === 6)->count());
    }

    public function test_unmappable_open_category_cannot_use_overflow(): void
    {
        $this->courts([2, 6]);
        $champ = $this->championship();
        $this->category($champ, 10, ['gender' => 'mixed', 'level' => 1]);
        $this->category($champ, 10, ['level' => null]);
        $this->fails($champ, 'No existe una asignación completa');
        $this->assertSame(0, Round::count());
    }

    public function test_later_week_failure_preserves_old_calendar_exactly(): void
    {
        $venues = $this->courts([2]);
        $champ = $this->championship();
        $category = $this->category($champ, 4);
        $this->generate($champ);
        $before = DB::table('game_matches')->orderBy('id')->get();
        $oldRounds = DB::table('rounds')->orderBy('id')->get();
        $champ->update(['start_date' => '2026-07-24']);
        $externalRound = Round::factory()->create();
        for ($day = 0; $day < 2; $day++) {
            for ($hour = 17; $hour <= 20; $hour++) {
                GameMatch::factory()->create([
                    'round_id' => $externalRound->id, 'venue_id' => $venues[2]->id,
                    'scheduled_date' => Carbon::parse('2026-08-07')->addDays($day)->setTime($hour, 0),
                    'status' => 'scheduled',
                ]);
            }
        }
        $this->fails($champ, 'Jornada 3');
        $this->assertEquals($before, DB::table('game_matches')->whereIn('id', $before->pluck('id'))->orderBy('id')->get());
        $this->assertEquals($oldRounds, DB::table('rounds')->where('category_id', $category->id)->orderBy('id')->get());
    }

    public function test_replacement_accepts_legacy_overlapping_scheduled_fixtures_without_history(): void
    {
        $venues = $this->courts([2]);
        $champ = $this->championship();
        $category = $this->category($champ, 4);
        $oldRound = Round::factory()->create([
            'category_id' => $category->id, 'type' => 'league', 'phase' => null, 'stage' => null,
        ]);
        $entries = $category->entries()->orderBy('id')->get();
        foreach (['17:30:00', '18:00:00'] as $time) {
            GameMatch::factory()->create([
                'round_id' => $oldRound->id, 'venue_id' => $venues[2]->id,
                'home_entry_id' => $entries[0]->id, 'away_entry_id' => $entries[1]->id,
                'scheduled_date' => '2026-07-03 '.$time, 'status' => 'scheduled',
            ]);
        }
        $oldMatchIds = $oldRound->matches()->pluck('id');
        $this->generate($champ);
        $this->assertDatabaseMissing('rounds', ['id' => $oldRound->id]);
        $this->assertSame(0, GameMatch::whereKey($oldMatchIds)->count());
        $this->assertSame(6, GameMatch::count());
        $this->assertTrue(GameMatch::get()->every(fn ($m) => $m->scheduled_date->format('i:s') === '00:00'));
    }

    public function test_impossible_global_capacity_creates_no_partial_fresh_calendar(): void
    {
        $this->courts([2]);
        $champ = $this->championship();
        $this->category($champ, 10);
        $this->category($champ, 10);
        $this->fails($champ, 'Jornada 1');
        $this->assertSame(0, Round::count());
        $this->assertSame(0, GameMatch::count());
    }

    #[DataProvider('unsafeMatchHistory')]
    public function test_result_or_operational_history_blocks_replacement(array $changes): void
    {
        $this->courts([2]);
        $champ = $this->championship();
        $category = $this->category($champ, 2);
        $this->generate($champ);
        $match = $this->leagueMatches($category)->sole();
        $match->update($changes);
        $before = $match->fresh()->getAttributes();
        $this->fails($champ, 'historia');
        $this->assertSame($before, $match->fresh()->getAttributes());
    }

    public static function unsafeMatchHistory(): array
    {
        return [
            'submitted' => [['status' => 'submitted']],
            'review' => [['status' => 'under_review']],
            'validated' => [['status' => 'validated']],
            'zero score' => [['home_score' => 0]],
            'postponed' => [['status' => 'postponed']],
            'cancelled' => [['status' => 'cancelled']],
        ];
    }

    #[DataProvider('dependentHistory')]
    public function test_direct_history_and_cup_block_replacement(string $kind): void
    {
        $this->courts([2]);
        $champ = $this->championship();
        $category = $this->category($champ, 2);
        $this->generate($champ);
        $match = $this->leagueMatches($category)->sole();
        if ($kind === 'cup') {
            Round::factory()->create(['category_id' => $category->id, 'type' => 'cup']);
        } elseif ($kind === 'ambiguous_cup') {
            $match->round->update(['phase' => 'cup', 'stage' => 'semifinal']);
        } elseif ($kind === 'official') {
            CategoryOfficialResult::factory()->reopened()->create(['category_id' => $category->id]);
        } else {
            $player = $match->homeEntry->player;
            $base = ['game_match_id' => $match->id, 'player_id' => $player->id, 'user_id' => $player->user_id, 'side' => 'home', 'status' => 'submitted'];
            DB::table($kind)->insert($base + ($kind === 'match_result_reports'
                ? ['home_score' => 10, 'away_score' => 2]
                : ['requested_venue_id' => $match->venue_id, 'requested_scheduled_date' => '2026-07-03 20:00:00']));
        }
        $this->fails($champ, 'No se puede regenerar');
        $this->assertDatabaseHas('game_matches', ['id' => $match->id]);
        $this->assertSame(1, $this->leagueMatches($category)->count());
    }

    public static function dependentHistory(): array
    {
        return array_map(fn ($v) => [$v], ['cup', 'ambiguous_cup', 'official', 'match_result_reports', 'match_reschedule_requests']);
    }

    public function test_championship_admin_action_and_removed_category_endpoint(): void
    {
        $this->courts([2]);
        $champ = $this->championship();
        $category = $this->category($champ, 2);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.championships.show', $champ))->assertOk()->assertSee('Generar calendario de liga');
        $this->actingAs($admin)->post('/admin/categories/'.$category->id.'/generate-league')->assertNotFound();
        $this->actingAs($admin)->from(route('admin.championships.show', $champ))
            ->post(route('admin.championships.generate-league', $champ))
            ->assertRedirect(route('admin.championships.show', $champ))->assertSessionHas('success');
        $this->get(route('admin.championships.show', $champ))->assertOk()->assertSee('Regenerar calendario de liga');
        $this->assertSame(1, GameMatch::count());
        $champ->update(['end_date' => null]);
        $this->post(route('admin.championships.generate-league', $champ))->assertSessionHas('error');
        $this->assertSame(1, GameMatch::count());
    }

    private function championship(array $changes = []): Championship
    {
        return Championship::factory()->create($changes + [
            'type' => 'singles', 'start_date' => '2026-07-03', 'end_date' => '2026-09-11',
        ]);
    }

    private function category(Championship $champ, int $count, array $changes = []): Category
    {
        $category = Category::factory()->create($changes + [
            'championship_id' => $champ->id, 'age_group' => 'open', 'gender' => 'female', 'level' => 2,
        ]);
        for ($i = 0; $i < $count; $i++) {
            if ($champ->type->value === 'doubles') {
                $team = Team::factory()->create(['category_id' => $category->id]);
                CategoryEntry::factory()->create(['category_id' => $category->id, 'entry_type' => 'team', 'player_id' => null, 'team_id' => $team->id, 'status' => 'approved']);
            } else {
                CategoryEntry::factory()->playerEntry()->create(['category_id' => $category->id, 'status' => 'approved']);
            }
        }

        return $category;
    }

    private function courts(array $numbers = [1, 2, 3, 4, 5, 6]): array
    {
        $venues = [];
        foreach ($numbers as $number) {
            $venues[$number] = Venue::factory()->create(['court_number' => $number, 'name' => 'Etiqueta ajena '.$number]);
        }

        return $venues;
    }

    private function generate(Championship $champ): void
    {
        app(GenerateLeagueScheduleService::class)->generate($champ);
    }

    private function leagueMatches(Category $category)
    {
        return GameMatch::with('venue', 'round')->whereHas('round', fn ($q) => $q->where('category_id', $category->id)->where('type', 'league'))->orderBy('id')->get();
    }

    private function fails(Championship $champ, string $message): void
    {
        try {
            $this->generate($champ);
            $this->fail('La generación debía fallar.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }
    }
}
