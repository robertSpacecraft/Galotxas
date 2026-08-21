<?php

namespace Tests\Feature\Api\V1;

use App\Models\NewsArticle;
use App\Services\Media\Exceptions\MediaStorageException;
use App\Services\Media\MediaStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class NewsArticleImageApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media_local');
        config()->set('media.disk', 'media_local');
    }

    public function test_local_image_is_publicly_indexable_only_for_an_effective_article(): void
    {
        $published = NewsArticle::factory()->published()->create(['slug' => 'publica']);
        $draft = NewsArticle::factory()->withImage()->draft()->create(['slug' => 'borrador']);
        $future = NewsArticle::factory()->scheduled()->create(['slug' => 'futura']);
        Storage::disk('media_local')->put($published->image_key, 'public-image');
        Storage::disk('media_local')->put($draft->image_key, 'draft-image');
        Storage::disk('media_local')->put($future->image_key, 'future-image');

        $this->get(route('api.v1.news.image', ['slug' => 'publica']))
            ->assertOk()
            ->assertHeader('X-Accel-Redirect', '/_private-media/'.$published->image_key)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeaderMissing('X-Robots-Tag');

        $this->get(route('api.v1.news.image', ['slug' => 'borrador']))->assertNotFound();
        $this->get(route('api.v1.news.image', ['slug' => 'futura']))->assertNotFound();
        $this->get('/api/v1/news/inexistente/image')->assertNotFound();
    }

    public function test_missing_object_returns_404_and_storage_failure_is_sanitized(): void
    {
        $missing = NewsArticle::factory()->published()->create(['slug' => 'sin-objeto']);
        $this->get(route('api.v1.news.image', ['slug' => $missing->slug]))->assertNotFound();

        $broken = NewsArticle::factory()->published()->create(['slug' => 'storage-caido']);
        $storage = Mockery::mock(MediaStorageService::class);
        $storage->shouldReceive('exists')
            ->once()
            ->with($broken->image_key)
            ->andThrow(new MediaStorageException('SECRET-KEY-DO-NOT-LEAK'));
        $this->app->instance(MediaStorageService::class, $storage);

        $response = $this->get(route('api.v1.news.image', ['slug' => $broken->slug]));

        $response->assertStatus(503);
        $this->assertStringNotContainsString('SECRET-KEY-DO-NOT-LEAK', $response->getContent());
    }

    public function test_s3_image_redirects_to_a_short_lived_public_url(): void
    {
        $article = NewsArticle::factory()->published()->create(['slug' => 'imagen-s3']);
        config()->set('media.disk', 'media_s3');
        $storage = Mockery::mock(MediaStorageService::class);
        $storage->shouldReceive('exists')->once()->with($article->image_key)->andReturnTrue();
        $storage->shouldReceive('temporaryUrl')
            ->once()
            ->with($article->image_key, false)
            ->andReturn('https://objects.example.test/signed-news-cover');
        $storage->shouldNotReceive('metadata');
        $storage->shouldNotReceive('readStream');
        $this->app->instance(MediaStorageService::class, $storage);

        $this->get(route('api.v1.news.image', ['slug' => $article->slug]))
            ->assertRedirect('https://objects.example.test/signed-news-cover')
            ->assertHeaderMissing('X-Robots-Tag');
    }
}
