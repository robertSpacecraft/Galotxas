<?php

namespace Tests\Unit\Media;

use App\Services\Media\ImagePreparationPolicy;
use App\Services\Media\MediaObjectKeyGenerator;
use App\Services\Media\ResponsiveImagePreparer;
use App\Services\Media\ResponsiveImageProfile;
use App\Services\Media\ResponsiveMediaResolver;
use App\Services\Media\ResponsiveMediaStorage;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ResponsiveMediaResolverTest extends TestCase
{
    use ResponsiveImageFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('media.disk', 'media_local');
        config()->set('cache.default', 'array');
        Storage::fake('media_local');
        Cache::store('array')->flush();
        CarbonImmutable::setTestNow('2026-09-08 10:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_positive_and_negative_results_expire_after_their_distinct_ttls(): void
    {
        $stored = $this->stored();
        $resolver = app(ResponsiveMediaResolver::class);
        $disk = Storage::disk('media_local');
        $manifestJson = $disk->get($stored->manifestKey);

        $this->assertNotNull($resolver->manifest($stored->masterKey, ResponsiveImageProfile::Banner));
        $disk->delete($stored->manifestKey);

        CarbonImmutable::setTestNow('2026-09-08 10:09:59');
        $this->assertNotNull($resolver->manifest($stored->masterKey, ResponsiveImageProfile::Banner));
        CarbonImmutable::setTestNow('2026-09-08 10:10:01');
        $this->assertNull($resolver->manifest($stored->masterKey, ResponsiveImageProfile::Banner));

        $disk->put($stored->manifestKey, $manifestJson, ['visibility' => 'private']);
        CarbonImmutable::setTestNow('2026-09-08 10:11:00');
        $this->assertNull($resolver->manifest($stored->masterKey, ResponsiveImageProfile::Banner));
        CarbonImmutable::setTestNow('2026-09-08 10:11:02');
        $this->assertNotNull($resolver->manifest($stored->masterKey, ResponsiveImageProfile::Banner));
    }

    public function test_missing_invalid_or_wrong_profile_manifest_keeps_only_the_master_contract(): void
    {
        $stored = $this->stored();
        $resolver = app(ResponsiveMediaResolver::class);
        $disk = Storage::disk('media_local');
        $master = 'https://api.example.test/api/v1/seasons/1/image';
        $expected = ['url' => $master, 'width' => 1920, 'height' => 1080];

        $disk->delete($stored->manifestKey);
        $this->assertSame($expected, $resolver->image(
            $stored->masterKey,
            ResponsiveImageProfile::Banner,
            $master,
            fn (int $width): string => $master.'/'.$width,
            1920,
            1080,
        ));

        Cache::store('array')->flush();
        $disk->put($stored->manifestKey, '{invalid');
        $this->assertSame($expected, $resolver->image(
            $stored->masterKey,
            ResponsiveImageProfile::Banner,
            $master,
            fn (int $width): string => $master.'/'.$width,
            1920,
            1080,
        ));

        Cache::store('array')->flush();
        $disk->put($stored->manifestKey, $stored->manifest->toJson());
        $this->assertSame($expected, $resolver->image(
            $stored->masterKey,
            ResponsiveImageProfile::NewsCover,
            $master,
            fn (int $width): string => $master.'/'.$width,
            1920,
            1080,
        ));
    }

    public function test_cache_store_acquisition_failure_still_reads_a_valid_manifest_directly(): void
    {
        $stored = $this->stored();
        $cache = Mockery::mock(CacheFactory::class);
        $cache->shouldReceive('store')->once()->andThrow(new RuntimeException('cache unavailable'));
        $resolver = new ResponsiveMediaResolver(
            app(ResponsiveMediaStorage::class),
            app(MediaObjectKeyGenerator::class),
            $cache,
        );
        $master = 'https://api.example.test/api/v1/seasons/1/image';

        $image = $resolver->image(
            $stored->masterKey,
            ResponsiveImageProfile::Banner,
            $master,
            fn (int $width): string => $master.'/'.$width,
            1600,
            900,
        );

        $this->assertSame($master, $image['url']);
        $this->assertSame(1600, $image['width']);
        $this->assertSame(900, $image['height']);
        $this->assertNotEmpty($image['variants']);
    }

    private function stored()
    {
        return app(ResponsiveMediaStorage::class)->store(
            app(ResponsiveImagePreparer::class)->prepare(
                $this->uploadBytes($this->fixtureBytes(1600, 900)),
                ResponsiveImageProfile::Banner,
                ImagePreparationPolicy::Photo,
            )
        );
    }
}
