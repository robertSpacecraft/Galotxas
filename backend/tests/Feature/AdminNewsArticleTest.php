<?php

namespace Tests\Feature;

use App\Enums\NewsArticlePublicationState;
use App\Enums\NewsArticleStatus;
use App\Models\NewsArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminNewsArticleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media_local');
        config()->set('media.disk', 'media_local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_news_admin_requires_an_active_administrator(): void
    {
        $normal = User::factory()->create();
        $inactive = User::factory()->admin()->create(['active' => false]);

        $this->get(route('admin.news-articles.index'))->assertRedirect(route('admin.login'));
        $this->actingAs($normal)->get(route('admin.news-articles.index'))->assertForbidden();
        $this->actingAs($inactive)
            ->get(route('admin.news-articles.index'))
            ->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.news-articles.index'))
            ->assertOk()
            ->assertSee('Noticias');
    }

    public function test_admin_creates_a_draft_with_generated_unique_slug_and_private_rights_data(): void
    {
        $admin = User::factory()->admin()->create();
        NewsArticle::factory()->create(['slug' => 'jornada-de-galotxas']);

        $this->actingAs($admin)
            ->post(route('admin.news-articles.store'), $this->payload([
                'title' => 'Jornada de Galotxas',
                'slug' => '',
                'image' => UploadedFile::fake()->image('cover.png', 2400, 1200),
                'image_alt' => 'Pelota y guantes sobre una pista vacía.',
                'image_source' => 'Fixture generada sin personas.',
                'image_rights_confirmed' => '1',
            ]))
            ->assertSessionHasNoErrors();

        $article = NewsArticle::query()->where('slug', 'jornada-de-galotxas-2')->sole();
        $this->assertSame(NewsArticleStatus::DRAFT, $article->status);
        $this->assertNull($article->published_at);
        $this->assertSame($admin->id, $article->image_rights_confirmed_by);
        $this->assertSame([1920, 960], [$article->image_width, $article->image_height]);
        Storage::disk('media_local')->assertExists($article->image_key);
    }

    public function test_admin_publishes_now_schedules_and_returns_articles_to_draft(): void
    {
        Carbon::setTestNow('2026-08-21 10:00:00');
        $admin = User::factory()->admin()->create();
        $immediate = $this->draftWithStoredImage();

        $this->actingAs($admin)
            ->put(
                route('admin.news-articles.update', $immediate),
                $this->payloadFor($immediate, [
                    'status' => 'published',
                    'published_at' => '',
                    'image_rights_confirmed' => '1',
                ])
            )
            ->assertSessionHasNoErrors();

        $immediate->refresh();
        $this->assertSame('2026-08-21 10:00:00', $immediate->published_at->format('Y-m-d H:i:s'));
        $this->assertSame(NewsArticlePublicationState::PUBLISHED, $immediate->publicationState());

        $this->actingAs($admin)
            ->put(
                route('admin.news-articles.update', $immediate),
                $this->payloadFor($immediate, [
                    'status' => 'draft',
                    'published_at' => '2026-08-21T10:00',
                ])
            )
            ->assertSessionHasNoErrors();

        $immediate->refresh();
        $this->assertSame(NewsArticleStatus::DRAFT, $immediate->status);
        $this->assertSame('2026-08-21 10:00:00', $immediate->published_at->format('Y-m-d H:i:s'));

        $scheduled = $this->draftWithStoredImage(['slug' => 'programada']);
        $this->actingAs($admin)
            ->put(
                route('admin.news-articles.update', $scheduled),
                $this->payloadFor($scheduled, [
                    'status' => 'published',
                    'published_at' => '2026-08-22T12:00',
                    'image_rights_confirmed' => '1',
                ])
            )
            ->assertSessionHasNoErrors();

        $this->assertSame(
            NewsArticlePublicationState::SCHEDULED,
            $scheduled->refresh()->publicationState()
        );

    }

    public function test_slug_and_effective_publication_date_become_immutable(): void
    {
        Carbon::setTestNow('2026-08-21 10:00:00');
        $admin = User::factory()->admin()->create();
        $article = $this->draftWithStoredImage();

        $this->actingAs($admin)
            ->put(route('admin.news-articles.update', $article), $this->payloadFor($article, [
                'status' => 'published',
                'image_rights_confirmed' => '1',
            ]))
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->put(route('admin.news-articles.update', $article), $this->payloadFor($article->refresh(), [
                'slug' => 'slug-cambiado',
                'published_at' => '2026-08-22T10:00',
                'image_rights_confirmed' => '1',
            ]))
            ->assertSessionHasErrors(['slug', 'published_at']);

        $article->refresh();
        $this->assertSame('noticia-de-prueba', $article->slug);
        $this->assertSame('2026-08-21 10:00:00', $article->published_at->format('Y-m-d H:i:s'));

    }

    public function test_soft_deleted_slug_stays_reserved(): void
    {
        $admin = User::factory()->admin()->create();
        $deleted = NewsArticle::factory()->create(['slug' => 'slug-reservado']);
        $deleted->delete();

        $this->actingAs($admin)
            ->post(route('admin.news-articles.store'), $this->payload([
                'slug' => 'slug-reservado',
            ]))
            ->assertSessionHasErrors('slug');

        $this->assertDatabaseCount('news_articles', 1);
    }

    public function test_publication_requires_image_alt_source_and_explicit_rights_confirmation(): void
    {
        $admin = User::factory()->admin()->create();
        $withoutImage = NewsArticle::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.news-articles.update', $withoutImage), $this->payloadFor($withoutImage, [
                'status' => 'published',
                'image_alt' => '',
                'image_source' => '',
                'image_rights_confirmed' => '0',
            ]))
            ->assertSessionHasErrors([
                'image',
                'image_alt',
                'image_source',
                'image_rights_confirmed',
            ]);

        $this->assertSame(NewsArticleStatus::DRAFT, $withoutImage->refresh()->status);
    }

    public function test_admin_rejects_html_oversized_body_and_unsupported_or_forged_images(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.news-articles.store'), $this->payload([
                'body' => '<script>alert(1)</script>',
            ]))
            ->assertSessionHasErrors('body');

        $this->actingAs($admin)
            ->post(route('admin.news-articles.store'), $this->payload([
                'body' => str_repeat('a', 20001),
            ]))
            ->assertSessionHasErrors('body');

        foreach ([
            UploadedFile::fake()->createWithContent('fake.png', 'not an image'),
            UploadedFile::fake()->create('vector.svg', 1, 'image/svg+xml'),
            UploadedFile::fake()->create('animation.gif', 1, 'image/gif'),
            UploadedFile::fake()->create('photo.avif', 1, 'image/avif'),
        ] as $image) {
            $this->actingAs($admin)
                ->post(route('admin.news-articles.store'), $this->payload(['image' => $image]))
                ->assertSessionHasErrors('image');
        }
    }

    public function test_replacing_image_records_new_rights_and_removing_it_requires_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $article = $this->draftWithStoredImage();
        $oldKey = $article->image_key;

        $this->actingAs($admin)
            ->put(route('admin.news-articles.update', $article), $this->payloadFor($article, [
                'image' => UploadedFile::fake()->image('replacement.webp', 600, 900),
                'image_alt' => 'Nueva imagen sin personas.',
                'image_credit' => '',
                'image_source' => 'Nueva fixture automatizada.',
                'image_rights_confirmed' => '1',
            ]))
            ->assertSessionHasNoErrors();

        $article->refresh();
        $this->assertNotSame($oldKey, $article->image_key);
        $this->assertSame($admin->id, $article->image_rights_confirmed_by);
        Storage::disk('media_local')->assertMissing($oldKey);
        Storage::disk('media_local')->assertExists($article->image_key);

        $this->actingAs($admin)
            ->put(route('admin.news-articles.update', $article), $this->payloadFor($article, [
                'status' => 'published',
                'remove_image' => '1',
                'image_rights_confirmed' => '1',
            ]))
            ->assertSessionHasErrors('remove_image');

        $keyToRemove = $article->image_key;
        $this->actingAs($admin)
            ->put(route('admin.news-articles.update', $article), $this->payloadFor($article, [
                'status' => 'draft',
                'remove_image' => '1',
            ]))
            ->assertSessionHasNoErrors();

        $article->refresh();
        $this->assertNull($article->image_key);
        $this->assertNull($article->image_rights_confirmed_at);
        Storage::disk('media_local')->assertMissing($keyToRemove);
    }

    public function test_admin_soft_deletes_article_and_cleans_its_image(): void
    {
        $admin = User::factory()->admin()->create();
        $article = $this->draftWithStoredImage();
        $key = $article->image_key;

        $this->actingAs($admin)
            ->delete(route('admin.news-articles.destroy', $article))
            ->assertRedirect(route('admin.news-articles.index'));

        $this->assertSoftDeleted($article);
        Storage::disk('media_local')->assertMissing($key);
        $this->assertFalse(Route::has('admin.news-articles.show'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function draftWithStoredImage(array $overrides = []): NewsArticle
    {
        $article = NewsArticle::factory()->withImage()->create([
            'title' => 'Noticia de prueba',
            'slug' => 'noticia-de-prueba',
            'status' => NewsArticleStatus::DRAFT->value,
            'published_at' => null,
            ...$overrides,
        ]);
        Storage::disk('media_local')->put($article->image_key, 'fixture-image');

        return $article;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Noticia de prueba',
            'slug' => 'noticia-de-prueba',
            'excerpt' => 'Resumen editorial manual de la noticia.',
            'body' => "Primer párrafo.\n\nSegundo párrafo.",
            'image_alt' => '',
            'image_credit' => '',
            'image_source' => '',
            'image_rights_confirmed' => '0',
            'remove_image' => '0',
            'status' => 'draft',
            'published_at' => '',
            'seo_title' => '',
            'seo_description' => '',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payloadFor(NewsArticle $article, array $overrides = []): array
    {
        return $this->payload([
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'body' => $article->body,
            'image_alt' => $article->image_alt ?? '',
            'image_credit' => $article->image_credit ?? '',
            'image_source' => $article->image_source ?? '',
            'status' => $article->status->value,
            'published_at' => $article->published_at?->format('Y-m-d\TH:i') ?? '',
            'seo_title' => $article->seo_title ?? '',
            'seo_description' => $article->seo_description ?? '',
            ...$overrides,
        ]);
    }
}
