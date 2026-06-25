<?php

namespace Database\Factories;

use App\Enums\CmsPageStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CmsPage>
 */
class CmsPageFactory extends Factory
{
    public function definition(): array
    {
        $title = $this->faker->sentence(3);

        return [
            'slug' => Str::slug($title . '-' . Str::random(5)),
            'title' => $title,
            'status' => CmsPageStatus::DRAFT->value,
            'published_at' => null,
            'seo_title' => $title,
            'seo_description' => $this->faker->sentence(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => CmsPageStatus::PUBLISHED->value,
            'published_at' => now()->subDay(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => CmsPageStatus::DRAFT->value,
            'published_at' => null,
        ]);
    }
}
