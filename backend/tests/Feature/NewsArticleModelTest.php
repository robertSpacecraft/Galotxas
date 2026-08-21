<?php

namespace Tests\Feature;

use App\Enums\NewsArticlePublicationState;
use App\Enums\NewsArticleStatus;
use App\Models\NewsArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsArticleModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_casts_and_publication_states_are_explicit(): void
    {
        $draft = NewsArticle::factory()->draft()->create();
        $scheduled = NewsArticle::factory()->scheduled()->create();
        $published = NewsArticle::factory()->published()->create();

        $this->assertSame(NewsArticleStatus::DRAFT, $draft->status);
        $this->assertSame(NewsArticlePublicationState::DRAFT, $draft->publicationState());
        $this->assertSame(NewsArticlePublicationState::SCHEDULED, $scheduled->publicationState());
        $this->assertSame(NewsArticlePublicationState::PUBLISHED, $published->publicationState());
        $this->assertIsInt($published->image_width);
        $this->assertTrue($published->hasBeenEffectivelyPublished());
        $this->assertFalse($published->isSlugEditable());
    }

    public function test_effective_scope_excludes_draft_future_and_soft_deleted_articles(): void
    {
        $visible = NewsArticle::factory()->published()->create();
        NewsArticle::factory()->draft()->create();
        NewsArticle::factory()->scheduled()->create();
        $deleted = NewsArticle::factory()->published()->create();
        $deleted->delete();

        $this->assertSame(
            [$visible->id],
            NewsArticle::query()->effectivelyPublished()->pluck('id')->all()
        );
    }

    public function test_newest_first_uses_published_at_then_id_for_a_stable_order(): void
    {
        $older = NewsArticle::factory()->published()->create([
            'published_at' => now()->subDays(2),
        ]);
        $firstTie = NewsArticle::factory()->published()->create([
            'published_at' => now()->subDay(),
        ]);
        $secondTie = NewsArticle::factory()->published()->create([
            'published_at' => now()->subDay(),
        ]);

        $this->assertSame(
            [$secondTie->id, $firstTie->id, $older->id],
            NewsArticle::query()
                ->effectivelyPublished()
                ->newestFirst()
                ->pluck('id')
                ->all()
        );
    }
}
