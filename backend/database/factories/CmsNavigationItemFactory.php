<?php

namespace Database\Factories;

use App\Enums\CmsNavigationSlot;
use App\Models\CmsNavigationItem;
use App\Models\CmsPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CmsNavigationItem>
 */
class CmsNavigationItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cms_page_id' => CmsPage::factory(),
            'slot' => CmsNavigationSlot::CLUB->value,
            'label' => $this->faker->words(3, true),
            'sort_order' => 0,
            'is_active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
