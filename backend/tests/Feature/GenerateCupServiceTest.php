<?php

namespace Tests\Feature;

use App\Enums\GameMatchStatus;
use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\CategoryOfficialResult;
use App\Models\Championship;
use App\Models\GameMatch;
use App\Models\Round;
use App\Models\User;
use App\Models\Venue;
use App\Services\ChampionshipCalendarWindow;
use App\Services\GenerateCupService;
use App\Services\Ranking\BuildCategoryRankingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class GenerateCupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cup_with_validated_semifinals_generates_finals_when_status_is_cast_to_enum(): void
    {
        [$category, $entries] = $this->createValidatedSemifinals();

        $this->assertInstanceOf(GameMatchStatus::class, GameMatch::query()->first()->status);

        app(GenerateCupService::class)->generateFinals($category);

        $finalRound = Round::query()
            ->where('category_id', $category->id)
            ->where('type', 'cup')
            ->where('name', 'Final')
            ->firstOrFail();

        $thirdPlaceRound = Round::query()
            ->where('category_id', $category->id)
            ->where('type', 'cup')
            ->where('name', '3º y 4º')
            ->firstOrFail();

        $this->assertSame('cup', $finalRound->phase);
        $this->assertSame('final', $finalRound->stage);
        $this->assertSame('cup', $thirdPlaceRound->phase);
        $this->assertSame('third_place', $thirdPlaceRound->stage);

        $this->assertDatabaseHas('game_matches', [
            'round_id' => $finalRound->id,
            'home_entry_id' => $entries[0]->id,
            'away_entry_id' => $entries[2]->id,
            'status' => GameMatchStatus::SCHEDULED->value,
            'scheduled_date' => '2026-09-11 19:00:00',
            'venue_id' => Venue::where('court_number', 2)->value('id'),
        ]);

        $this->assertDatabaseHas('game_matches', [
            'round_id' => $thirdPlaceRound->id,
            'home_entry_id' => $entries[1]->id,
            'away_entry_id' => $entries[3]->id,
            'status' => GameMatchStatus::SCHEDULED->value,
            'scheduled_date' => '2026-09-11 17:00:00',
            'venue_id' => Venue::where('court_number', 2)->value('id'),
        ]);
    }

    public function test_cup_finals_are_not_generated_without_semifinals(): void
    {
        [$category] = $this->createCategoryWithApprovedEntries();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No existen semifinales.');

        app(GenerateCupService::class)->generateFinals($category);
    }

    public function test_cup_finals_require_exactly_two_semifinal_matches(): void
    {
        [$category, $entries] = $this->createCategoryWithApprovedEntries();
        $semifinalRound = Round::factory()->create([
            'category_id' => $category->id,
            'name' => 'Semifinales',
            'order' => 100,
            'type' => 'cup',
            'phase' => 'cup',
            'stage' => 'semifinal',
        ]);
        GameMatch::factory()->create([
            'round_id' => $semifinalRound->id,
            'home_entry_id' => $entries[0]->id,
            'away_entry_id' => $entries[1]->id,
            'status' => 'validated',
            'home_score' => 10,
            'away_score' => 7,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Las semifinales no están correctamente definidas.');

        app(GenerateCupService::class)->generateFinals($category);
    }

    public function test_cup_finals_are_not_generated_if_semifinals_are_not_validated(): void
    {
        [$category] = $this->createSemifinals(GameMatchStatus::SUBMITTED);

        try {
            app(GenerateCupService::class)->generateFinals($category);
            $this->fail('Finals should not be generated before semifinals are validated.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Las semifinales deben estar validadas antes de generar la final.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('rounds', [
            'category_id' => $category->id,
            'type' => 'cup',
            'name' => 'Final',
        ]);

        $this->assertDatabaseMissing('rounds', [
            'category_id' => $category->id,
            'type' => 'cup',
            'name' => '3º y 4º',
        ]);
    }

    public function test_cup_finals_are_not_generated_from_a_tied_semifinal(): void
    {
        [$category] = $this->createValidatedSemifinals();
        $category->rounds()
            ->where('stage', 'semifinal')
            ->firstOrFail()
            ->matches()
            ->firstOrFail()
            ->update([
                'home_score' => 10,
                'away_score' => 10,
                'winner_entry_id' => null,
            ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Las semifinales no pueden terminar en empate.');

        app(GenerateCupService::class)->generateFinals($category);
    }

    public function test_cup_finals_are_recreated_without_duplicates_when_they_already_exist(): void
    {
        [$category] = $this->createValidatedSemifinals();
        $semifinals = $this->cupMatches($category)->toArray();

        app(GenerateCupService::class)->generateFinals($category);
        $oldFinalIds = $this->cupMatches($category)->where('status', GameMatchStatus::SCHEDULED)->modelKeys();
        app(GenerateCupService::class)->generateFinals($category);

        $this->assertSame(0, GameMatch::whereKey($oldFinalIds)->count());
        $this->assertSame($semifinals, $this->cupMatches($category)->where('status', GameMatchStatus::VALIDATED)->values()->toArray());

        $this->assertSame(1, Round::query()
            ->where('category_id', $category->id)
            ->where('type', 'cup')
            ->where('name', 'Final')
            ->count());

        $this->assertSame(1, Round::query()
            ->where('category_id', $category->id)
            ->where('type', 'cup')
            ->where('name', '3º y 4º')
            ->count());

        $this->assertSame(1, $this->cupRoundMatchCount($category, 'Final'));
        $this->assertSame(1, $this->cupRoundMatchCount($category, '3º y 4º'));
    }

    public function test_generate_semifinals_keeps_normal_cup_generation_working(): void
    {
        [$category] = $this->createCategoryWithApprovedEntries();

        app(GenerateCupService::class)->generateSemifinals($category);

        $semifinalRound = Round::query()
            ->where('category_id', $category->id)
            ->where('type', 'cup')
            ->where('name', 'Semifinales')
            ->firstOrFail();

        $this->assertSame(2, $semifinalRound->matches()->count());
        $this->assertSame('cup', $semifinalRound->phase);
        $this->assertSame('semifinal', $semifinalRound->stage);
    }

    public function test_generate_semifinals_uses_the_ranking_corrected_for_close_results(): void
    {
        [$category, $entries] = $this->createCategoryWithApprovedEntries();
        $leagueRound = Round::factory()->create([
            'category_id' => $category->id,
            'type' => 'league',
            'phase' => 'league',
            'stage' => 'matchday',
        ]);

        GameMatch::factory()->create([
            'round_id' => $leagueRound->id,
            'home_entry_id' => $entries[0]->id,
            'away_entry_id' => $entries[2]->id,
            'status' => 'validated',
            'home_score' => 10,
            'away_score' => 8,
        ]);
        GameMatch::factory()->create([
            'round_id' => $leagueRound->id,
            'home_entry_id' => $entries[0]->id,
            'away_entry_id' => $entries[3]->id,
            'status' => 'validated',
            'home_score' => 10,
            'away_score' => 8,
        ]);
        GameMatch::factory()->create([
            'round_id' => $leagueRound->id,
            'home_entry_id' => $entries[1]->id,
            'away_entry_id' => $entries[2]->id,
            'status' => 'validated',
            'home_score' => 10,
            'away_score' => 0,
        ]);
        GameMatch::factory()->create([
            'round_id' => $leagueRound->id,
            'home_entry_id' => $entries[3]->id,
            'away_entry_id' => $entries[1]->id,
            'status' => 'validated',
            'home_score' => 10,
            'away_score' => 8,
        ]);

        $ranking = app(BuildCategoryRankingService::class)->build($category);

        $this->assertSame(
            [$entries[1]->id, $entries[0]->id, $entries[3]->id, $entries[2]->id],
            $ranking->pluck('entry_id')->all()
        );
        $this->assertSame([4, 4, 3, 1], $ranking->pluck('points')->all());

        app(GenerateCupService::class)->generateSemifinals($category);

        $semifinalMatches = Round::query()
            ->where('category_id', $category->id)
            ->where('type', 'cup')
            ->where('name', 'Semifinales')
            ->firstOrFail()
            ->matches()
            ->orderBy('id')
            ->get();

        $this->assertSame($entries[1]->id, $semifinalMatches[0]->home_entry_id);
        $this->assertSame($entries[2]->id, $semifinalMatches[0]->away_entry_id);
        $this->assertSame($entries[0]->id, $semifinalMatches[1]->home_entry_id);
        $this->assertSame($entries[3]->id, $semifinalMatches[1]->away_entry_id);
    }

    public function test_generated_final_can_be_scheduled_from_the_admin_flow(): void
    {
        [$category] = $this->createValidatedSemifinals();
        app(GenerateCupService::class)->generateFinals($category);

        $final = Round::query()
            ->where('category_id', $category->id)
            ->where('stage', 'final')
            ->firstOrFail()
            ->matches()
            ->firstOrFail();
        $venue = Venue::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('admin.categories.matches.update', [$category, $final]), [
                'scheduled_date' => '2026-09-20',
                'scheduled_time' => '19:00',
                'venue_id' => $venue->id,
                'status' => 'scheduled',
                'home_score' => null,
                'away_score' => null,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('game_matches', [
            'id' => $final->id,
            'venue_id' => $venue->id,
            'scheduled_date' => '2026-09-20 19:00:00',
            'status' => 'scheduled',
            'home_score' => null,
            'away_score' => null,
        ]);
    }

    public function test_admin_cannot_move_a_generated_cup_final_into_a_globally_occupied_slot(): void
    {
        [$category] = $this->createValidatedSemifinals();
        app(GenerateCupService::class)->generateFinals($category);

        $final = Round::query()
            ->where('category_id', $category->id)
            ->where('stage', 'final')
            ->firstOrFail()
            ->matches()
            ->firstOrFail();
        $venue = Venue::factory()->create();
        GameMatch::factory()->create([
            'venue_id' => $venue->id,
            'scheduled_date' => '2026-09-20 19:00:00',
            'status' => 'scheduled',
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.categories.matches.update', [$category, $final]), [
                'scheduled_date' => '2026-09-20',
                'scheduled_time' => '19:00',
                'venue_id' => $venue->id,
                'status' => 'scheduled',
                'home_score' => null,
                'away_score' => null,
            ])
            ->assertSessionHas(
                'error',
                'La pista seleccionada ya está ocupada en esa fecha y hora por otro partido.'
            );

        $this->assertDatabaseHas('game_matches', [
            'id' => $final->id,
            'venue_id' => $final->venue_id,
            'scheduled_date' => $final->scheduled_date,
            'status' => 'scheduled',
        ]);
    }

    #[DataProvider('modalityProvider')]
    public function test_first_male_semifinals_use_general_courts_on_shared_first_cup_friday(string $type): void
    {
        [$category] = $this->createCategoryWithApprovedEntries();
        $category->update(['age_group' => 'open', 'gender' => 'male', 'level' => 1]);
        $category->championship->update(['type' => $type]);
        $ranking = app(BuildCategoryRankingService::class)->build($category);
        app(GenerateCupService::class)->generateSemifinals($category);
        $matches = $category->rounds()->where('stage', 'semifinal')->firstOrFail()->matches()->orderBy('id')->get();
        $friday = app(ChampionshipCalendarWindow::class)->weekends($category->championship, 0)['cup'][0];
        $this->assertSame([2, 3], $matches->map(fn ($m) => $m->venue->court_number)->all());
        foreach ($matches as $match) {
            $this->assertSame($friday->format('Y-m-d').' 17:00:00', $match->scheduled_date->format('Y-m-d H:i:s'));
            $this->assertTrue($match->scheduled_date->isFriday());
        }
        $this->assertSame([$ranking[0]['entry_id'], $ranking[1]['entry_id']], $matches->pluck('home_entry_id')->all());
        $this->assertSame([$ranking[3]['entry_id'], $ranking[2]['entry_id']], $matches->pluck('away_entry_id')->all());
    }

    public static function modalityProvider(): array
    {
        return [['singles'], ['doubles']];
    }

    public function test_semifinals_prefer_later_normal_capacity_over_early_overflow_and_respect_legacy_occupancy(): void
    {
        [$category] = $this->createCategoryWithApprovedEntries();
        foreach ([2, 3, 4, 5] as $court) {
            $this->occupy($court, '2026-09-04 17:30:00');
        }
        app(GenerateCupService::class)->generateSemifinals($category);
        $matches = $this->cupMatches($category);
        $this->assertSame([2, 3], $matches->map(fn ($m) => $m->venue->court_number)->all());
        $this->assertSame(['19:00:00', '19:00:00'], $matches->map(fn ($m) => $m->scheduled_date->format('H:i:s'))->all());
    }

    public function test_semifinals_use_overflow_only_after_exhausting_normal_capacity(): void
    {
        [$category] = $this->createCategoryWithApprovedEntries();
        foreach ([17, 18, 19, 20] as $hour) {
            foreach ([2, 3, 4, 5] as $court) {
                if ($court !== 5 || $hour !== 20) {
                    $this->occupy($court, "2026-09-04 {$hour}:00:00");
                }
            }
        }
        app(GenerateCupService::class)->generateSemifinals($category);
        $matches = $this->cupMatches($category);
        $this->assertSame([5, 6], $matches->map(fn ($m) => $m->venue->court_number)->all());
        $this->assertSame(['20:00', '17:00'], $matches->map(fn ($m) => $m->scheduled_date->format('H:i'))->all());
    }

    public function test_impossible_semifinals_restore_old_pair_and_ignore_extra_or_unclassified_venues(): void
    {
        [$category] = $this->createCategoryWithApprovedEntries();
        app(GenerateCupService::class)->generateSemifinals($category);
        $before = $this->cupMatches($category)->toArray();
        // Removing metadata, not fixture rows: no permitted physical capacity remains.
        Venue::query()->update(['court_number' => null]);
        Venue::factory()->create(['court_number' => 7, 'name' => 'Pista 2']);
        $this->assertCupFailure(fn () => app(GenerateCupService::class)->generateSemifinals($category), 'No hay pistas permitidas');
        $this->assertSame($before, $this->cupMatches($category)->toArray());
    }

    #[DataProvider('finalMetadataProvider')]
    public function test_final_time_is_structural_and_third_place_is_always_second_friday_at_17(string $age, string $gender, int $level, int $hour): void
    {
        [$category] = $this->createValidatedSemifinals();
        $category->update(['name' => 'Primera masculina juvenil', 'age_group' => $age, 'gender' => $gender, 'level' => $level]);
        app(GenerateCupService::class)->generateFinals($category);
        $final = $category->rounds()->where('stage', 'final')->firstOrFail()->matches()->firstOrFail();
        $third = $category->rounds()->where('stage', 'third_place')->firstOrFail()->matches()->firstOrFail();
        $this->assertSame("2026-09-11 {$hour}:00:00", $final->scheduled_date->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-11 17:00:00', $third->scheduled_date->format('Y-m-d H:i:s'));
        $this->assertSame(2, $final->venue->court_number);
        $this->assertSame(2, $third->venue->court_number);
        $this->assertTrue($final->scheduled_date->isFriday());
    }

    public static function finalMetadataProvider(): array
    {
        return [
            ['youth', 'male', 1, 18], ['youth', 'female', 6, 18], ['youth', 'mixed', 2, 18],
            ['open', 'mixed', 1, 18], ['open', 'female', 1, 19], ['open', 'male', 2, 20],
        ];
    }

    #[DataProvider('modalityProvider')]
    public function test_first_male_final_has_required_court_but_third_place_uses_general_policy(string $type): void
    {
        [$category] = $this->createValidatedSemifinals();
        $category->update(['gender' => 'male', 'level' => 1]);
        $category->championship->update(['type' => $type]);
        app(GenerateCupService::class)->generateFinals($category);
        $final = $category->rounds()->where('stage', 'final')->firstOrFail()->matches()->firstOrFail();
        $third = $category->rounds()->where('stage', 'third_place')->firstOrFail()->matches()->firstOrFail();
        $this->assertSame($type === 'singles' ? 4 : 1, $final->venue->court_number);
        $this->assertSame('2026-09-11 20:00:00', $final->scheduled_date->format('Y-m-d H:i:s'));
        $this->assertSame(2, $third->venue->court_number);
    }

    #[DataProvider('requiredCourtFailureProvider')]
    public function test_missing_or_occupied_required_final_court_fails_without_partial_pair(string $type, bool $missing): void
    {
        [$category] = $this->createValidatedSemifinals();
        $category->update(['gender' => 'male', 'level' => 1]);
        $category->championship->update(['type' => $type]);
        $court = $type === 'singles' ? 4 : 1;
        if ($missing) {
            Venue::where('court_number', $court)->update(['court_number' => null]);
        } else {
            $this->occupy($court, '2026-09-11 20:00:00');
        }
        $before = $this->cupMatches($category)->toArray();
        $this->assertCupFailure(fn () => app(GenerateCupService::class)->generateFinals($category), $missing ? "Falta la pista {$court}" : 'No hay pistas permitidas');
        $this->assertSame(1, $category->rounds()->count());
        $this->assertSame($before, $this->cupMatches($category)->toArray());
    }

    public static function requiredCourtFailureProvider(): array
    {
        return [['singles', true], ['singles', false], ['doubles', true], ['doubles', false]];
    }

    public function test_general_final_and_third_place_use_overflow_only_when_normal_courts_are_occupied(): void
    {
        [$category] = $this->createValidatedSemifinals();
        foreach ([17, 19] as $hour) {
            foreach ([2, 3, 4, 5] as $court) {
                $this->occupy($court, "2026-09-11 {$hour}:00:00");
            }
        }
        app(GenerateCupService::class)->generateFinals($category);
        $new = $this->cupMatches($category)->where('status', GameMatchStatus::SCHEDULED);
        $this->assertCount(2, $new);
        $this->assertSame([6, 6], $new->map(fn ($m) => $m->venue->court_number)->values()->all());
    }

    public function test_final_replacement_failure_restores_previous_pair_and_validated_semifinals(): void
    {
        [$category] = $this->createValidatedSemifinals();
        app(GenerateCupService::class)->generateFinals($category);
        $before = $this->cupMatches($category)->toArray();
        // A changed window makes the new third-place impossible; the old pair
        // must survive even though it was deleted before authoritative allocation.
        $category->championship->update(['end_date' => '2026-09-18']);
        foreach ([2, 3, 4, 5, 6] as $court) {
            $this->occupy($court, '2026-09-18 17:00:00');
        }
        $this->assertCupFailure(fn () => app(GenerateCupService::class)->generateFinals($category), '3º y 4º');
        $this->assertSame($before, $this->cupMatches($category)->toArray());
        $this->assertSame(3, $category->rounds()->count());
    }

    public function test_history_free_semifinals_can_be_replaced_or_deleted(): void
    {
        [$category] = $this->createCategoryWithApprovedEntries();
        $service = app(GenerateCupService::class);
        $service->generateSemifinals($category);
        $oldIds = $this->cupMatches($category)->modelKeys();
        $service->generateSemifinals($category);
        $this->assertSame(0, GameMatch::whereKey($oldIds)->count());
        $this->assertCount(2, $this->cupMatches($category));
        $service->deleteCup($category);
        $this->assertSame(0, $category->rounds()->count());
    }

    #[DataProvider('historyProvider')]
    public function test_history_blocks_semifinal_replacement_and_cup_deletion(string $history): void
    {
        [$category, $entries] = $this->createCategoryWithApprovedEntries();
        $service = app(GenerateCupService::class);
        $service->generateSemifinals($category);
        $match = $this->cupMatches($category)->first();
        if ($history === 'official') {
            CategoryOfficialResult::factory()->cup()->reopened()->create(['category_id' => $category->id]);
        } elseif ($history === 'request') {
            $match->rescheduleRequests()->create([
                'user_id' => $entries[0]->player->user_id,
                'player_id' => $entries[0]->player_id,
                'side' => 'home', 'requested_scheduled_date' => '2026-09-04 20:00:00',
                'requested_venue_id' => $match->venue_id, 'status' => 'submitted',
            ]);
        } elseif ($history === 'report') {
            $match->resultReports()->create([
                'user_id' => $entries[0]->player->user_id,
                'player_id' => $entries[0]->player_id, 'side' => 'home',
                'home_score' => 10, 'away_score' => 5, 'status' => 'submitted',
            ]);
        } elseif ($history === 'score') {
            $match->update(['home_score' => 0]);
        } else {
            $match->update(['status' => $history]);
        }
        $before = $this->cupMatches($category)->toArray();
        $this->assertCupFailure(fn () => $service->generateSemifinals($category), 'No se puede');
        $this->assertCupFailure(fn () => $service->deleteCup($category), 'No se puede');
        $this->assertSame($before, $this->cupMatches($category)->toArray());
    }

    public static function historyProvider(): array
    {
        return [['submitted'], ['under_review'], ['validated'], ['score'], ['request'], ['report'], ['official']];
    }

    public function test_results_in_final_block_replacement_without_touching_semifinals(): void
    {
        [$category] = $this->createValidatedSemifinals();
        app(GenerateCupService::class)->generateFinals($category);
        $category->rounds()->where('stage', 'final')->firstOrFail()->matches()->update(['home_score' => 1]);
        $before = $this->cupMatches($category)->toArray();
        $this->assertCupFailure(fn () => app(GenerateCupService::class)->generateFinals($category), 'hay resultados');
        $this->assertSame($before, $this->cupMatches($category)->toArray());
    }

    public function test_null_age_group_blocks_both_generation_steps(): void
    {
        [$category] = $this->createValidatedSemifinals();
        $category->update(['age_group' => null]);
        $before = $this->cupMatches($category)->toArray();
        $this->assertCupFailure(fn () => app(GenerateCupService::class)->generateSemifinals($category), 'Clasifica el grupo de edad');
        $this->assertCupFailure(fn () => app(GenerateCupService::class)->generateFinals($category), 'Clasifica el grupo de edad');
        $this->assertSame($before, $this->cupMatches($category)->toArray());
    }

    public function test_insufficient_window_and_participants_leave_no_cup_structure(): void
    {
        [$category, $entries] = $this->createCategoryWithApprovedEntries();
        $entries->last()->update(['status' => 'pending']);
        $this->assertCupFailure(fn () => app(GenerateCupService::class)->generateSemifinals($category), 'al menos 4');
        $entries->last()->update(['status' => 'approved']);
        $category->championship->update(['end_date' => '2026-07-03']);
        $this->assertCupFailure(fn () => app(GenerateCupService::class)->generateSemifinals($category), 'dos semanas reservadas');
        $this->assertSame(0, $category->rounds()->count());
    }

    public function test_category_admin_cup_actions_remain_available_and_schedule_matches(): void
    {
        [$category] = $this->createCategoryWithApprovedEntries();
        $this->actingAs(User::factory()->admin()->create());
        $this->post(route('admin.categories.generate-cup', $category))->assertSessionHas('success');
        $this->assertCount(2, $this->cupMatches($category));
        foreach ($this->cupMatches($category) as $match) {
            $this->assertNotNull($match->scheduled_date);
            $this->assertNotNull($match->venue_id);
            $match->update(['status' => 'validated', 'home_score' => 10, 'away_score' => 5]);
        }
        $this->post(route('admin.categories.generate-finals', $category))->assertSessionHas('success');
        $this->assertCount(4, $this->cupMatches($category));
        $this->delete(route('admin.categories.delete-cup', $category))->assertSessionHas('error');
        $this->assertCount(4, $this->cupMatches($category));
    }

    private function assertCupFailure(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail('Expected controlled Cup failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }
    }

    private function occupy(int $court, string $date): void
    {
        GameMatch::factory()->create([
            'venue_id' => Venue::where('court_number', $court)->value('id'),
            'scheduled_date' => $date, 'status' => 'scheduled',
        ]);
    }

    private function cupMatches(Category $category): Collection
    {
        return GameMatch::whereIn('round_id', $category->rounds()->where('type', 'cup')->select('id'))->orderBy('id')->get();
    }

    private function createValidatedSemifinals(): array
    {
        return $this->createSemifinals(GameMatchStatus::VALIDATED);
    }

    private function createSemifinals(GameMatchStatus $status): array
    {
        [$category, $entries] = $this->createCategoryWithApprovedEntries();

        $semifinalRound = Round::query()->create([
            'category_id' => $category->id,
            'name' => 'Semifinales',
            'order' => 100,
            'type' => 'cup',
            'phase' => 'cup',
            'stage' => 'semifinal',
        ]);

        GameMatch::query()->create([
            'round_id' => $semifinalRound->id,
            'venue_id' => null,
            'home_entry_id' => $entries[0]->id,
            'away_entry_id' => $entries[1]->id,
            'scheduled_date' => null,
            'status' => $status->value,
            'home_score' => 10,
            'away_score' => 5,
        ]);

        GameMatch::query()->create([
            'round_id' => $semifinalRound->id,
            'venue_id' => null,
            'home_entry_id' => $entries[2]->id,
            'away_entry_id' => $entries[3]->id,
            'scheduled_date' => null,
            'status' => $status->value,
            'home_score' => 10,
            'away_score' => 6,
        ]);

        return [$category, $entries];
    }

    private function createCategoryWithApprovedEntries(): array
    {
        // Deliberately unrelated IDs/names: only court_number is authoritative.
        foreach ([6, 4, 1, 5, 3, 2] as $number) {
            Venue::factory()->create(['court_number' => $number, 'name' => 'Recinto '.(10 - $number)]);
        }
        $championship = Championship::factory()->create([
            'type' => 'singles', 'start_date' => '2026-07-03', 'end_date' => '2026-09-11',
        ]);
        $category = Category::factory()->create([
            'championship_id' => $championship->id, 'age_group' => 'open', 'gender' => 'female', 'level' => 2,
        ]);
        $entries = collect();

        foreach (range(1, 4) as $position) {
            $entries->push(CategoryEntry::factory()->playerEntry()->create([
                'category_id' => $category->id,
                'status' => 'approved',
            ]));
        }

        return [$category, $entries->values()];
    }

    private function cupRoundMatchCount(Category $category, string $name): int
    {
        $round = Round::query()
            ->where('category_id', $category->id)
            ->where('type', 'cup')
            ->where('name', $name)
            ->firstOrFail();

        return $round->matches()->count();
    }
}
