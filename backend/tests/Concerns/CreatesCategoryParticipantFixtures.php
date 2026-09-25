<?php

namespace Tests\Concerns;

use App\Enums\OfficialResultCompetitionPart;
use App\Models\Category;
use App\Models\CategoryOfficialResult;
use App\Models\CategoryRegistration;
use App\Models\Championship;
use App\Models\ChampionshipRegistrationRequest;
use App\Models\Player;
use App\Models\Team;

trait CreatesCategoryParticipantFixtures
{
    protected function categoryOfType(string $type): Category
    {
        return Category::factory()->create([
            'championship_id' => Championship::factory()->create(['type' => $type])->id,
        ]);
    }

    protected function registeredPlayer(Category $category, string $status = 'approved'): Player
    {
        $player = Player::factory()->create();

        CategoryRegistration::factory()->create([
            'category_id' => $category->id,
            'player_id' => $player->id,
            'status' => $status,
        ]);

        return $player;
    }

    protected function withApprovedChampionshipRequest(Category $category, Player $player): void
    {
        ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $category->championship_id,
            'user_id' => $player->user_id,
            'player_id' => $player->id,
            'status' => 'approved',
        ]);
    }

    /**
     * A valid doubles team: two registered players, front and back, in the category.
     *
     * @return array{team: Team, front: Player, back: Player}
     */
    protected function doublesTeam(Category $category): array
    {
        $front = $this->registeredPlayer($category);
        $back = $this->registeredPlayer($category);
        $team = Team::factory()->create(['category_id' => $category->id]);
        $team->players()->attach($front->id, ['role_in_team' => 'front']);
        $team->players()->attach($back->id, ['role_in_team' => 'back']);

        return ['team' => $team, 'front' => $front, 'back' => $back];
    }

    protected function officialResult(Category $category, OfficialResultCompetitionPart $part): CategoryOfficialResult
    {
        return CategoryOfficialResult::factory()->create([
            'category_id' => $category->id,
            'competition_part' => $part->value,
        ]);
    }
}
