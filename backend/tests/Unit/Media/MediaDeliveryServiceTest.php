<?php

namespace Tests\Unit\Media;

use App\Services\Media\ManifestImage;
use App\Services\Media\MediaDeliveryService;
use App\Services\Media\MediaStorageService;
use DateTimeInterface;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Mockery;
use Tests\TestCase;

class MediaDeliveryServiceTest extends TestCase
{
    public function test_local_sponsor_variant_matches_master_cache_and_indexing_policy(): void
    {
        config()->set('media.disk', 'media_local');
        $masterKey = 'sponsors/00000000-0000-4000-8000-000000000001.png';
        $image = ManifestImage::fromObject((object) [
            'key' => 'variants/v1/sponsors/00000000-0000-4000-8000-000000000001/w160.webp',
            'width' => 160,
            'height' => 80,
            'mime_type' => 'image/webp',
            'size' => 1024,
        ]);
        $storage = Mockery::mock(MediaStorageService::class);
        $storage->shouldReceive('exists')->once()->with($masterKey)->andReturnTrue();
        $storage->shouldReceive('metadata')->once()->with($masterKey)->andReturn([
            'size' => 2048,
            'mime_type' => 'image/png',
            'last_modified' => 1,
        ]);
        $filesystems = Mockery::mock(FilesystemManager::class);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $filesystems->shouldReceive('disk')->once()->with('media_local')->andReturn($disk);
        $disk->shouldReceive('exists')->once()->with($image->key)->andReturnTrue();
        $delivery = new MediaDeliveryService($storage, $filesystems);

        $master = $delivery->deliver($masterKey);
        $variant = $delivery->deliverVariant($image);

        $this->assertSame($master->headers->get('Cache-Control'), $variant->headers->get('Cache-Control'));
        $this->assertSame($master->headers->get('X-Robots-Tag'), $variant->headers->get('X-Robots-Tag'));
        $this->assertSame('no-store, private', $variant->headers->get('Cache-Control'));
        $this->assertSame('noindex, nofollow', $variant->headers->get('X-Robots-Tag'));
        $this->assertSame('/_private-media/'.$image->key, $variant->headers->get('X-Accel-Redirect'));
        $this->assertSame('image/webp', $variant->headers->get('Content-Type'));
    }

    public function test_public_s3_delivery_has_explicit_indexable_semantics(): void
    {
        config()->set('media.disk', 'media_s3');
        $key = 'news/00000000-0000-4000-8000-000000000001.webp';
        $storage = Mockery::mock(MediaStorageService::class);
        $storage->shouldReceive('exists')->once()->with($key)->andReturnTrue();
        $storage->shouldReceive('temporaryUrl')
            ->once()
            ->with($key, false)
            ->andReturn('https://objects.example.test/signed-news-cover');

        $response = (new MediaDeliveryService($storage, app(FilesystemManager::class)))
            ->deliverPublic($key);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(
            'https://objects.example.test/signed-news-cover',
            $response->headers->get('Location')
        );
        $this->assertSame('max-age=60, public', $response->headers->get('Cache-Control'));
        $this->assertFalse($response->headers->has('X-Robots-Tag'));
    }

    public function test_s3_delivery_redirects_to_a_short_lived_url_without_exposing_the_key_in_json(): void
    {
        config()->set('media.disk', 'media_s3');
        $storage = Mockery::mock(MediaStorageService::class);
        $storage->shouldReceive('exists')->once()->with('sponsors/00000000-0000-4000-8000-000000000001.png')->andReturnTrue();
        $storage->shouldReceive('temporaryUrl')
            ->once()
            ->with('sponsors/00000000-0000-4000-8000-000000000001.png', false)
            ->andReturn('https://objects.example.test/signed-logo');

        $response = (new MediaDeliveryService($storage, app(FilesystemManager::class)))->deliver(
            'sponsors/00000000-0000-4000-8000-000000000001.png'
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('https://objects.example.test/signed-logo', $response->headers->get('Location'));
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
    }

    public function test_s3_sponsor_variant_redirect_matches_master_cache_and_indexing_policy(): void
    {
        config()->set('media.disk', 'media_s3');
        $image = ManifestImage::fromObject((object) [
            'key' => 'variants/v1/sponsors/00000000-0000-4000-8000-000000000001/w160.webp',
            'width' => 160,
            'height' => 80,
            'mime_type' => 'image/webp',
            'size' => 1024,
        ]);
        $storage = Mockery::mock(MediaStorageService::class);
        $filesystems = Mockery::mock(FilesystemManager::class);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $filesystems->shouldReceive('disk')->once()->with('media_s3')->andReturn($disk);
        $disk->shouldReceive('exists')->once()->with($image->key)->andReturnTrue();
        $disk->shouldReceive('temporaryUrl')
            ->once()
            ->with($image->key, Mockery::type(DateTimeInterface::class))
            ->andReturn('https://objects.example.test/signed-logo-variant');

        $response = (new MediaDeliveryService($storage, $filesystems))->deliverVariant($image);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(
            'https://objects.example.test/signed-logo-variant',
            $response->headers->get('Location')
        );
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
    }

    public function test_s3_private_delivery_streams_the_object_without_redirecting(): void
    {
        config()->set('media.disk', 'media_s3');
        $key = 'avatars/00000000-0000-4000-8000-000000000001.webp';
        $bytes = 'private-avatar-bytes';
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $bytes);
        rewind($stream);
        $storage = Mockery::mock(MediaStorageService::class);
        $storage->shouldReceive('exists')->once()->with($key)->andReturnTrue();
        $storage->shouldReceive('metadata')->once()->with($key)->andReturn([
            'size' => strlen($bytes),
            'mime_type' => 'image/webp',
            'last_modified' => 1,
        ]);
        $storage->shouldReceive('readStream')->once()->with($key)->andReturn($stream);
        $storage->shouldNotReceive('temporaryUrl');

        $response = (new MediaDeliveryService($storage, app(FilesystemManager::class)))
            ->deliverPrivate($key);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($response->headers->has('Location'));
        $this->assertSame('image/webp', $response->headers->get('Content-Type'));
        $this->assertSame((string) strlen($bytes), $response->headers->get('Content-Length'));
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));

        ob_start();
        $response->sendContent();
        $this->assertSame($bytes, ob_get_clean());
        $this->assertFalse(is_resource($stream));
    }

    public function test_s3_private_variant_also_streams_without_a_signed_redirect(): void
    {
        config()->set('media.disk', 'media_s3');
        $bytes = 'private-avatar-variant';
        $image = ManifestImage::fromObject((object) [
            'key' => 'variants/v1/avatars/00000000-0000-4000-8000-000000000001/w128.webp',
            'width' => 128,
            'height' => 128,
            'mime_type' => 'image/webp',
            'size' => strlen($bytes),
        ]);
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $bytes);
        rewind($stream);
        $storage = Mockery::mock(MediaStorageService::class);
        $filesystems = Mockery::mock(FilesystemManager::class);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $filesystems->shouldReceive('disk')->once()->with('media_s3')->andReturn($disk);
        $disk->shouldReceive('exists')->once()->with($image->key)->andReturnTrue();
        $disk->shouldReceive('readStream')->once()->with($image->key)->andReturn($stream);
        $disk->shouldNotReceive('temporaryUrl');

        $response = (new MediaDeliveryService($storage, $filesystems))
            ->deliverPrivateVariant($image);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($response->headers->has('Location'));
        $this->assertSame('image/webp', $response->headers->get('Content-Type'));
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
        ob_start();
        $response->sendContent();
        $this->assertSame($bytes, ob_get_clean());
        $this->assertFalse(is_resource($stream));
    }
}
