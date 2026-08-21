<?php

namespace Database\Factories;

use App\Enums\NewsArticleStatus;
use App\Models\NewsArticle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NewsArticle>
 */
class NewsArticleFactory extends Factory
{
    public function definition(): array
    {
        $title = $this->faker->sentence(5);

        return [
            'title' => $title,
            'slug' => Str::slug($title.'-'.Str::random(6)),
            'excerpt' => $this->faker->sentence(14),
            'body' => $this->faker->paragraphs(3, true),
            'image_key' => null,
            'image_width' => null,
            'image_height' => null,
            'image_alt' => null,
            'image_credit' => null,
            'image_source' => null,
            'image_rights_confirmed_at' => null,
            'image_rights_confirmed_by' => null,
            'status' => NewsArticleStatus::DRAFT->value,
            'published_at' => null,
            'seo_title' => null,
            'seo_description' => null,
        ];
    }

    public function withImage(): static
    {
        return $this->state(fn () => [
            'image_key' => sprintf('news/%s.png', Str::uuid()),
            'image_width' => 1600,
            'image_height' => 900,
            'image_alt' => 'Imagen generada para una noticia de prueba.',
            'image_credit' => null,
            'image_source' => 'Fixture automatizada sin personas identificables.',
            'image_rights_confirmed_at' => now(),
            'image_rights_confirmed_by' => User::factory()->admin(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => NewsArticleStatus::DRAFT->value,
            'published_at' => null,
        ]);
    }

    public function scheduled(): static
    {
        return $this->withImage()->state(fn () => [
            'status' => NewsArticleStatus::PUBLISHED->value,
            'published_at' => now()->addDay(),
        ]);
    }

    public function published(): static
    {
        return $this->withImage()->state(fn () => [
            'status' => NewsArticleStatus::PUBLISHED->value,
            'published_at' => now()->subDay(),
        ]);
    }
}
