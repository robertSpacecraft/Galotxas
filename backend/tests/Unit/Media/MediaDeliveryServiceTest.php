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
}
