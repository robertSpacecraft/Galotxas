<?php

namespace Tests\Feature;

use App\Enums\GameMatchStatus;
use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\GameMatch;
use App\Models\Round;
use App\Services\GenerateCupService;
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

        $this->assertDatabaseHas('game_matches', [
            'round_id' => $finalRound->id,
            'home_entry_id' => $entries[0]->id,
            'away_entry_id' => $entries[2]->id,
            'status' => GameMatchStatus::SCHEDULED->value,
        ]);

        $this->assertDatabaseHas('game_matches', [
            'round_id' => $thirdPlaceRound->id,
            'home_entry_id' => $entries[1]->id,
            'away_entry_id' => $entries[3]->id,
            'status' => GameMatchStatus::SCHEDULED->value,
        ]);
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
