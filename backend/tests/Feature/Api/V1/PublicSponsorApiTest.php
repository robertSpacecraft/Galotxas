<?php

namespace Tests\Feature\Api\V1;

use App\Models\Sponsor;
use App\Services\Media\Exceptions\MediaStorageException;
use App\Services\Media\MediaStorageService;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class PublicSponsorApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media_local');
        config()->set('media.disk', 'media_local');
    }

    public function test_public_collection_only_exposes_effective_sponsors_in_stable_order(): void
    {
        $now = CarbonImmutable::parse('2026-08-16 12:00:00');
        CarbonImmutable::setTestNow($now);
        $second = Sponsor::factory()->active()->create([
            'name' => 'Segundo por orden',
            'sort_order' => 20,
            'website_url' => null,
            'starts_at' => $now,
            'ends_at' => $now->addDay(),
        ]);
        $first = Sponsor::factory()->active()->create([
            'name' => 'Primero por orden',
            'sort_order' => 10,
            'website_url' => 'https://example.com',
        ]);
        Sponsor::factory()->inactive()->create();
        Sponsor::factory()->scheduled()->create();
        Sponsor::factory()->expired()->create();
        Sponsor::factory()->active()->create(['ends_at' => $now]);

        $response = $this->getJson('/api/v1/sponsors');

        $response
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson([
                'message' => null,
                'data' => [
                    [
                        'id' => $first->id,
                        'name' => 'Primero por orden',
                        'logo' => [
                            'url' => route('api.v1.sponsors.logo', $first),
                            'width' => 600,
                            'height' => 300,
                        ],
                        'website_url' => 'https://example.com',
                    ],
                    [
                        'id' => $second->id,
                        'name' => 'Segundo por orden',
                        'logo' => [
                            'url' => route('api.v1.sponsors.logo', $second),
                            'width' => 600,
                            'height' => 300,
                        ],
                        'website_url' => null,
                    ],
                ],
            ]);

        foreach (['logo_key', 'sort_order', 'is_active', 'starts_at', 'ends_at', 'created_at'] as $privateField) {
            $response->assertJsonMissing([$privateField]);
        }
    }

    public function test_public_collection_uses_the_standard_empty_envelope(): void
    {
        $this->getJson('/api/v1/sponsors')
            ->assertOk()
            ->assertExactJson([
                'message' => null,
                'data' => [],
            ]);
    }

    public function test_public_logo_is_fail_closed_and_serves_only_effective_existing_objects(): void
    {
        $active = Sponsor::factory()->active()->create();
        $inactive = Sponsor::factory()->inactive()->create();
        Storage::disk('media_local')->put($active->logo_key, 'active-logo');
        Storage::disk('media_local')->put($inactive->logo_key, 'inactive-logo');

        $this->get(route('api.v1.sponsors.logo', $active))
            ->assertOk()
            ->assertHeader('X-Accel-Redirect', '/_private-media/'.$active->logo_key)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get(route('api.v1.sponsors.logo', $inactive))->assertNotFound();

        Storage::disk('media_local')->delete($active->logo_key);
        $this->get(route('api.v1.sponsors.logo', $active))->assertNotFound();
        $this->get('/api/v1/sponsors/999999/logo')->assertNotFound();
    }

    public function test_storage_failure_returns_a_generic_503_without_leaking_internal_detail(): void
    {
        $sponsor = Sponsor::factory()->active()->create();
        $storage = Mockery::mock(MediaStorageService::class);
        $storage->shouldReceive('exists')
            ->once()
            ->andThrow(new MediaStorageException(
                'MEDIA_SECRET_ACCESS_KEY=do-not-leak'
            ));
        $this->app->instance(MediaStorageService::class, $storage);

        $response = $this->get(route('api.v1.sponsors.logo', $sponsor));

        $response->assertStatus(503);
        $this->assertStringNotContainsString('do-not-leak', $response->getContent());
    }

    public function test_public_sponsor_logo_keeps_its_s3_temporary_redirect(): void
    {
        $sponsor = Sponsor::factory()->active()->create();
        config()->set('media.disk', 'media_s3');
        $storage = Mockery::mock(MediaStorageService::class);
        $storage->shouldReceive('exists')
            ->once()
            ->with($sponsor->logo_key)
            ->andReturnTrue();
        $storage->shouldReceive('temporaryUrl')
            ->once()
            ->with($sponsor->logo_key, false)
            ->andReturn('https://objects.example.test/signed-sponsor-logo');
        $storage->shouldNotReceive('metadata');
        $storage->shouldNotReceive('readStream');
        $this->app->instance(MediaStorageService::class, $storage);

        $this->get(route('api.v1.sponsors.logo', $sponsor))
            ->assertRedirect('https://objects.example.test/signed-sponsor-logo');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        (new Filesystem)->deleteDirectory(storage_path('framework/testing/disks'));

        parent::tearDown();
    }
}
