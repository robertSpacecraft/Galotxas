<?php

namespace Tests\Feature\Api\V1;

use App\Models\NewsArticle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicNewsArticleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-08-21 12:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_list_only_exposes_effective_articles_in_stable_newest_first_order(): void
    {
        $older = NewsArticle::factory()->published()->create([
            'slug' => 'noticia-antigua',
            'title' => 'Noticia antigua',
            'published_at' => now()->subDays(2),
        ]);
        $newer = NewsArticle::factory()->published()->create([
            'slug' => 'noticia-reciente',
            'title' => 'Noticia reciente',
            'published_at' => now()->subDay(),
        ]);
        NewsArticle::factory()->draft()->create(['slug' => 'borrador']);
        NewsArticle::factory()->scheduled()->create(['slug' => 'programada']);
        $deleted = NewsArticle::factory()->published()->create(['slug' => 'eliminada']);
        $deleted->delete();

        $response = $this->getJson('/api/v1/news');

        $response
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('message', null)
            ->assertJsonPath('data.0.slug', $newer->slug)
            ->assertJsonPath('data.1.slug', $older->slug)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta', [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 12,
                'total' => 2,
                'has_more' => false,
            ]);

        foreach ([
            'body',
            'status',
            'image_key',
            'image_source',
            'image_rights_confirmed_at',
            'image_rights_confirmed_by',
            'deleted_at',
            'created_at',
            'updated_at',
        ] as $privateField) {
            $response->assertJsonMissing([$privateField]);
        }
    }

    public function test_list_paginates_twelve_and_out_of_range_pages_keep_coherent_meta(): void
    {
        NewsArticle::factory()->published()->count(13)->sequence(
            fn ($sequence) => [
                'slug' => 'noticia-'.$sequence->index,
                'published_at' => now()->subMinutes($sequence->index),
            ]
        )->create();

        $this->getJson('/api/v1/news')
            ->assertOk()
            ->assertJsonCount(12, 'data')
            ->assertJsonPath('data.0.slug', 'noticia-0')
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.has_more', true);

        $this->getJson('/api/v1/news?page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'noticia-12')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.has_more', false);

        $this->getJson('/api/v1/news?page=99')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.current_page', 99)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 12)
            ->assertJsonPath('meta.total', 13)
            ->assertJsonPath('meta.has_more', false);
    }

    public function test_detail_uses_a_closed_allowlist_and_the_stable_image_url(): void
    {
        $article = NewsArticle::factory()->published()->create([
            'slug' => 'cronica-final',
            'title' => 'Crónica de la final',
            'excerpt' => 'Resumen manual.',
            'body' => "Primer párrafo.\n\nSegundo párrafo.",
            'image_alt' => 'Pelota sobre una pista vacía.',
            'image_credit' => 'Club Galotxes Monòver',
            'image_source' => 'PRIVATE-SOURCE-MARKER',
            'seo_title' => 'Final de galotxas',
            'seo_description' => 'Descripción SEO manual.',
        ]);

        $response = $this->getJson('/api/v1/news/cronica-final');

        $response
            ->assertOk()
            ->assertExactJson([
                'message' => null,
                'data' => [
                    'slug' => 'cronica-final',
                    'title' => 'Crónica de la final',
                    'excerpt' => 'Resumen manual.',
                    'published_at' => $article->published_at->toIso8601String(),
                    'image' => [
                        'url' => route('api.v1.news.image', ['slug' => 'cronica-final']),
                        'width' => 1600,
                        'height' => 900,
                        'alt' => 'Pelota sobre una pista vacía.',
                        'credit' => 'Club Galotxes Monòver',
                    ],
                    'body' => "Primer párrafo.\n\nSegundo párrafo.",
                    'seo_title' => 'Final de galotxas',
                    'seo_description' => 'Descripción SEO manual.',
                ],
            ]);

        $payload = $response->getContent();
        foreach ([
            'image_key',
            'image_source',
            'PRIVATE-SOURCE-MARKER',
            'image_rights_confirmed_at',
            'image_rights_confirmed_by',
            'status',
            'deleted_at',
        ] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $payload);
        }
    }

    public function test_detail_returns_indistinguishable_404_for_non_public_records(): void
    {
        NewsArticle::factory()->draft()->create(['slug' => 'borrador']);
        NewsArticle::factory()->scheduled()->create(['slug' => 'programada']);
        $deleted = NewsArticle::factory()->published()->create(['slug' => 'eliminada']);
        $deleted->delete();

        foreach (['borrador', 'programada', 'eliminada', 'inexistente'] as $slug) {
            $this->getJson('/api/v1/news/'.$slug)->assertNotFound();
        }
    }
}
