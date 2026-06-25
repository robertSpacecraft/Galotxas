<?php

namespace Database\Factories;

use App\Enums\CmsBlockType;
use App\Models\CmsPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CmsBlock>
 */
class CmsBlockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cms_page_id' => CmsPage::factory(),
            'type' => CmsBlockType::TEXT->value,
            'sort_order' => 0,
            'data' => [
                'text' => $this->faker->paragraph(),
            ],
        ];
    }
}
