<?php

namespace Tests\Feature;

use App\Enums\GameMatchStatus;
use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\GameMatch;
use App\Models\Round;
use App\Models\User;
use App\Models\Venue;
use App\Services\GenerateCupService;
use App\Services\Ranking\BuildCategoryRankingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'scheduled_date' => null,
            'venue_id' => null,
        ]);

        $this->assertDatabaseHas('game_matches', [
            'round_id' => $thirdPlaceRound->id,
            'home_entry_id' => $entries[1]->id,
            'away_entry_id' => $entries[3]->id,
            'status' => GameMatchStatus::SCHEDULED->value,
            'scheduled_date' => null,
            'venue_id' => null,
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

        app(GenerateCupService::class)->generateFinals($category);
        app(GenerateCupService::class)->generateFinals($category);

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
                'scheduled_time' => '19:15',
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
            'scheduled_date' => '2026-09-20 19:15:00',
            'status' => 'scheduled',
            'home_score' => null,
            'away_score' => null,
        ]);
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
        $category = Category::factory()->create();
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
