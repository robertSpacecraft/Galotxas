<?php

namespace Tests\Feature;

use App\Enums\NewsArticleStatus;
use App\Models\NewsArticle;
use App\Models\User;
use App\Services\Media\Exceptions\MediaStorageException;
use App\Services\Media\ImageNormalizer;
use App\Services\Media\MediaObjectKeyGenerator;
use App\Services\Media\MediaStorageService;
use App\Services\NewsArticleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class NewsArticleLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media_local');
        config()->set('media.disk', 'media_local');
    }

    public function test_create_database_failure_compensates_the_new_object(): void
    {
        NewsArticle::creating(function (): void {
            throw new RuntimeException('forced database failure');
        });

        try {
            app(NewsArticleService::class)->create(
                $this->attributes(),
                UploadedFile::fake()->image('cover.png', 100, 50),
                User::factory()->admin()->create()
            );
            $this->fail('The forced database failure was not propagated.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced database failure', $exception->getMessage());
        } finally {
            NewsArticle::flushEventListeners();
        }

        $this->assertSame([], Storage::disk('media_local')->allFiles());
        $this->assertDatabaseCount('news_articles', 0);
    }

    public function test_replace_failure_preserves_old_reference_and_removes_new_object(): void
    {
        $article = NewsArticle::factory()->withImage()->create();
        Storage::disk('media_local')->put($article->image_key, 'old-image');
        NewsArticle::updating(function (): void {
            throw new RuntimeException('forced replace failure');
        });

        try {
            app(NewsArticleService::class)->update(
                $article,
                $this->attributes(['slug' => $article->slug]),
                UploadedFile::fake()->image('replacement.png', 80, 40),
                User::factory()->admin()->create()
            );
            $this->fail('The forced database failure was not propagated.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced replace failure', $exception->getMessage());
        } finally {
            NewsArticle::flushEventListeners();
        }

        $this->assertSame($article->image_key, $article->refresh()->image_key);
        $this->assertSame([$article->image_key], Storage::disk('media_local')->allFiles());
    }

    public function test_cleanup_failure_after_soft_delete_is_sanitized_without_restoring_article(): void
    {
        $article = NewsArticle::factory()->withImage()->create();
        $storage = Mockery::mock(MediaStorageService::class);
        $storage->shouldReceive('delete')
            ->once()
            ->with($article->image_key)
            ->andThrow(new MediaStorageException('secret object detail'));
        Log::shouldReceive('warning')
            ->once()
            ->with('News media cleanup failed.', ['operation' => 'delete_object']);
        $service = new NewsArticleService(
            app(ImageNormalizer::class),
            $storage,
            app(MediaObjectKeyGenerator::class)
        );

        $service->delete($article);

        $this->assertSoftDeleted($article);
    }

    public function test_stale_replacements_use_locked_current_reference(): void
    {
        $article = NewsArticle::factory()->withImage()->create();
        Storage::disk('media_local')->put($article->image_key, 'original');
        $firstRequest = NewsArticle::query()->findOrFail($article->id);
        $secondRequest = NewsArticle::query()->findOrFail($article->id);
        $service = app(NewsArticleService::class);
        $actor = User::factory()->admin()->create();

        $service->update(
            $firstRequest,
            $this->attributes(['slug' => $article->slug]),
            UploadedFile::fake()->image('first.png', 80, 40),
            $actor
        );
        $firstKey = $article->refresh()->image_key;

        $service->update(
            $secondRequest,
            $this->attributes(['slug' => $article->slug]),
            UploadedFile::fake()->image('second.png', 60, 30),
            $actor
        );
        $secondKey = $article->refresh()->image_key;

        $this->assertNotSame($firstKey, $secondKey);
        Storage::disk('media_local')->assertMissing($firstKey);
        Storage::disk('media_local')->assertExists($secondKey);
        $this->assertSame([$secondKey], Storage::disk('media_local')->allFiles());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function attributes(array $overrides = []): array
    {
        return [
            'title' => 'Noticia técnica',
            'slug' => null,
            'excerpt' => 'Resumen manual.',
            'body' => 'Contenido de texto plano.',
            'image_alt' => 'Imagen de prueba sin personas.',
            'image_credit' => null,
            'image_source' => 'Fixture automatizada.',
            'image_rights_confirmed' => true,
            'remove_image' => false,
            'status' => NewsArticleStatus::DRAFT->value,
            'published_at' => null,
            'seo_title' => null,
            'seo_description' => null,
            ...$overrides,
        ];
    }
}
