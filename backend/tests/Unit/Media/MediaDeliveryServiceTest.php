<?php

namespace Tests\Unit\Media;

use App\Services\Media\MediaDeliveryService;
use App\Services\Media\MediaStorageService;
use Mockery;
use Tests\TestCase;

class MediaDeliveryServiceTest extends TestCase
{
    public function test_s3_delivery_redirects_to_a_short_lived_url_without_exposing_the_key_in_json(): void
    {
        config()->set('media.disk', 'media_s3');
        $storage = Mockery::mock(MediaStorageService::class);
        $storage->shouldReceive('exists')->once()->with('sponsors/00000000-0000-4000-8000-000000000001.png')->andReturnTrue();
        $storage->shouldReceive('temporaryUrl')
            ->once()
            ->with('sponsors/00000000-0000-4000-8000-000000000001.png', false)
            ->andReturn('https://objects.example.test/signed-logo');

        $response = (new MediaDeliveryService($storage))->deliver(
            'sponsors/00000000-0000-4000-8000-000000000001.png'
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('https://objects.example.test/signed-logo', $response->headers->get('Location'));
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
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

        $response = (new MediaDeliveryService($storage))->deliverPrivate($key);

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
}
