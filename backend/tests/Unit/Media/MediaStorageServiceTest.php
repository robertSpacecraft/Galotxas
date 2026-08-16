<?php

namespace Tests\Unit\Media;

use App\Services\Media\Exceptions\MediaStorageException;
use App\Services\Media\MediaObjectKeyGenerator;
use App\Services\Media\MediaPurpose;
use App\Services\Media\MediaStorageService;
use App\Services\Media\NormalizedImage;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class MediaStorageServiceTest extends TestCase
{
    public function test_it_stores_reads_metadata_and_deletes_a_private_normalized_image(): void
    {
        Storage::fake('media_local');
        config()->set('media.disk', 'media_local');
        $service = app(MediaStorageService::class);
        $image = $this->normalizedImage();

        $key = $service->store(MediaPurpose::Avatar, $image);

        $this->assertMatchesRegularExpression(
            '/\Aavatars\/[0-9a-f-]{36}\.jpg\z/',
            $key,
        );
        $this->assertTrue($service->exists($key));
        $this->assertSame('private', Storage::disk('media_local')->visibility($key));
        $this->assertSame(strlen($image->bytes), $service->metadata($key)['size']);

        $service->delete($key);

        $this->assertFalse($service->exists($key));
    }

    public function test_key_generation_is_opaque_closed_and_not_derived_from_user_data(): void
    {
        $generator = app(MediaObjectKeyGenerator::class);

        foreach (MediaPurpose::cases() as $purpose) {
            $key = $generator->generate($purpose, 'JPG');

            $this->assertTrue($generator->isValid($key));
            $this->assertStringStartsWith($purpose->value.'/', $key);
            $this->assertStringNotContainsString('private-user-name', $key);
        }

        $this->expectException(InvalidArgumentException::class);
        $generator->generate(MediaPurpose::Cms, '../jpg');
    }

    public function test_sponsor_keys_use_the_dedicated_private_prefix(): void
    {
        $generator = app(MediaObjectKeyGenerator::class);
        $key = $generator->generate(MediaPurpose::Sponsor, 'png');

        $this->assertStringStartsWith('sponsors/', $key);
        $this->assertTrue($generator->isValid($key));
    }

    public function test_storage_operations_reject_path_traversal_before_touching_the_disk(): void
    {
        Storage::fake('media_local');
        config()->set('media.disk', 'media_local');

        $this->expectException(MediaStorageException::class);
        $this->expectExceptionMessage('clave multimedia');

        app(MediaStorageService::class)->exists('../../private.jpg');
    }

    public function test_storage_failure_is_propagated_without_returning_a_false_key_or_leaking_details(): void
    {
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('put')
            ->once()
            ->andThrow(new RuntimeException('MEDIA_SECRET_ACCESS_KEY=do-not-leak'));
        $manager = Mockery::mock(FilesystemManager::class);
        $manager->shouldReceive('disk')->once()->with('media_local')->andReturn($disk);
        $service = new MediaStorageService($manager, new MediaObjectKeyGenerator);

        try {
            $service->store(MediaPurpose::News, $this->normalizedImage());
            $this->fail('A storage failure returned successfully.');
        } catch (MediaStorageException $exception) {
            $this->assertSame('No se pudo almacenar el recurso multimedia.', $exception->getMessage());
            $this->assertStringNotContainsString('do-not-leak', (string) $exception);
            $this->assertNull($exception->getPrevious());
        }
    }

    public function test_temporary_url_uses_the_configured_ttl_without_persisting_the_url(): void
    {
        Storage::fake('media_local');
        config()->set('media.disk', 'media_local');
        config()->set('media.private_temporary_url_ttl_seconds', 37);
        $disk = Storage::disk('media_local');
        $disk->buildTemporaryUrlsUsing(
            fn (string $path, $expiration): string => 'https://temporary.test/'.$path
                .'?expires='.$expiration->getTimestamp()
        );
        $service = app(MediaStorageService::class);
        $key = $service->store(MediaPurpose::Cms, $this->normalizedImage());

        $url = $service->temporaryUrl($key, private: true);

        $this->assertStringStartsWith('https://temporary.test/cms/', $url);
        $this->assertStringContainsString('?expires=', $url);
        $this->assertSame([$key], $disk->allFiles());
    }

    public function test_filesystem_configuration_keeps_general_storage_local_and_media_private(): void
    {
        $this->assertSame('local', config('filesystems.default'));
        $this->assertSame('media_local', config('media.disk'));
        $this->assertSame(storage_path('app/media'), config('filesystems.disks.media_local.root'));
        $this->assertSame('private', config('filesystems.disks.media_local.visibility'));
        $this->assertSame(0640, config('filesystems.disks.media_local.permissions.file.private'));
        $this->assertSame(0750, config('filesystems.disks.media_local.permissions.dir.private'));
        $this->assertTrue(config('filesystems.disks.media_local.throw'));
        $this->assertSame('s3', config('filesystems.disks.media_s3.driver'));
        $this->assertSame('private', config('filesystems.disks.media_s3.visibility'));
        $this->assertTrue(config('filesystems.disks.media_s3.throw'));
        $this->assertArrayNotHasKey('media_local', config('filesystems.links'));
    }

    private function normalizedImage(): NormalizedImage
    {
        return new NormalizedImage(
            bytes: 'normalized-image-bytes',
            mimeType: 'image/jpeg',
            extension: 'jpg',
            width: 10,
            height: 10,
            size: 22,
        );
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory(storage_path('framework/testing/disks'));

        parent::tearDown();
    }
}
