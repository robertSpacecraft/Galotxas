<?php

namespace Database\Factories;

use App\Enums\OfficialResultCompetitionPart;
use App\Enums\OfficialResultStatus;
use App\Models\Category;
use App\Models\CategoryOfficialResult;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoryOfficialResult>
 */
class CategoryOfficialResultFactory extends Factory
{
    protected $model = CategoryOfficialResult::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'competition_part' => OfficialResultCompetitionPart::LEAGUE->value,
            'version' => 1,
            'status' => OfficialResultStatus::OFFICIAL->value,
            'officialized_at' => now(),
            'officialized_by_user_id' => User::factory(),
            'officialized_by_name_snapshot' => fake()->name(),
            'reopened_at' => null,
            'reopened_by_user_id' => null,
            'reopened_by_name_snapshot' => null,
            'reopen_reason' => null,
            'source_digest' => hash('sha256', fake()->uuid()),
        ];
    }

    public function league(): static
    {
        return $this->state(fn () => [
            'competition_part' => OfficialResultCompetitionPart::LEAGUE->value,
        ]);
    }

    public function cup(): static
    {
        return $this->state(fn () => [
            'competition_part' => OfficialResultCompetitionPart::CUP->value,
        ]);
    }

    public function reopened(): static
    {
        return $this->state(fn () => [
            'status' => OfficialResultStatus::REOPENED->value,
            'reopened_at' => now(),
            'reopened_by_user_id' => User::factory(),
            'reopened_by_name_snapshot' => fake()->name(),
            'reopen_reason' => fake()->sentence(),
        ]);
    }
}
