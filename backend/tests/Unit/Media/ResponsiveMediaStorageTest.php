<?php

namespace Tests\Unit\Media;

use App\Services\Media\Exceptions\MediaStorageException;
use App\Services\Media\ImageNormalizer;
use App\Services\Media\ImagePreparationPolicy;
use App\Services\Media\MediaDeliveryService;
use App\Services\Media\MediaObjectKeyGenerator;
use App\Services\Media\MediaPurpose;
use App\Services\Media\MediaStorageService;
use App\Services\Media\PreparedResponsiveSet;
use App\Services\Media\ResponsiveImagePreparer;
use App\Services\Media\ResponsiveImageProfile;
use App\Services\Media\ResponsiveManifest;
use App\Services\Media\ResponsiveMediaKeys;
use App\Services\Media\ResponsiveMediaStorage;
use App\Services\Media\VariantPolicyVersion;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\TestCase;

class ResponsiveMediaStorageTest extends TestCase
{
    use ResponsiveImageFixtures;

    private const MASTER = 'banners/550e8400-e29b-41d4-a716-446655440000.webp';

    public function test_private_local_round_trip_and_legacy_missing_manifest(): void
    {
        Storage::fake('media_local');
        config()->set('media.disk', 'media_local');
        $service = app(ResponsiveMediaStorage::class);
        $set = $this->prepared();
        $result = $service->store($set);
        $disk = Storage::disk('media_local');

        $this->assertSame($result->manifest->toJson(), $service->readManifest($result->masterKey)->toJson());
        $this->assertCount(4, $disk->allFiles());
        foreach ([$result->manifest->master, ...$result->manifest->variants] as $image) {
            $this->assertSame('private', $disk->visibility($image->key));
            $this->assertSame($image->size, $disk->size($image->key));
            $this->assertSame($image->mimeType, $disk->mimeType($image->key));
        }
        $this->assertSame('private', $disk->visibility($result->manifestKey));
        $this->assertSame('application/json', $disk->mimeType($result->manifestKey));
        $this->assertNull($service->readManifest(self::MASTER));
    }

    public function test_master_only_prepared_set_has_a_valid_private_manifest(): void
    {
        Storage::fake('media_local');
        config()->set('media.disk', 'media_local');
        $set = app(ResponsiveImagePreparer::class)->prepare(
            $this->uploadBytes($this->fixtureBytes(80, 40)), ResponsiveImageProfile::Banner, ImagePreparationPolicy::Graphic,
        );
        $service = app(ResponsiveMediaStorage::class);
        $result = $service->store($set);
        $this->assertSame([], $service->readManifest($result->masterKey)->variants);
        $this->assertCount(2, Storage::disk('media_local')->allFiles());
    }

    public function test_independent_rounding_of_tall_source_candidates_remains_readable(): void
    {
        Storage::fake('media_local');
        config()->set('media.disk', 'media_local');
        $set = app(ResponsiveImagePreparer::class)->prepare(
            $this->uploadBytes($this->fixtureBytes(1023, 3600)), ResponsiveImageProfile::SponsorLogo, ImagePreparationPolicy::Photo,
        );
        $this->assertSame([171, 600], [$set->master->width, $set->master->height]);
        $this->assertSame([160], array_column($set->variants, 'width'));
        $service = app(ResponsiveMediaStorage::class);
        $result = $service->store($set);
        $this->assertSame($result->manifest->toJson(), $service->readManifest($result->masterKey)->toJson());
    }

    public function test_s3_adapter_contract_writes_master_variants_manifest_last_with_private_content_types(): void
    {
        config()->set('media.disk', 'media_s3');
        $set = $this->prepared();
        $objects = [];
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->times(4)->andReturnFalse();
        $disk->shouldReceive('put')->times(4)->andReturnUsing(function ($key, $bytes, $options) use (&$objects) {
            $this->assertSame('private', $options['visibility']);
            if (str_ends_with($key, 'manifest.json')) {
                $this->assertCount(3, $objects);
                $this->assertSame('application/json', $options['ContentType']);
            } else {
                $this->assertSame('image/webp', $options['ContentType']);
            }
            $objects[$key] = $bytes;

            return true;
        });
        $result = $this->service($disk)->store($set);
        $this->assertSame($result->masterKey, array_key_first($objects));
        $this->assertSame($result->manifestKey, array_key_last($objects));
        $this->assertSame([320, 640], array_column($result->manifest->variants, 'width'));
        $this->assertSame($set->master->bytes, $objects[$result->masterKey]);
    }

    #[DataProvider('writeFailures')]
    public function test_each_failed_write_compensates_only_the_new_set_including_partial_puts(int $failure, bool $throws): void
    {
        config()->set('media.disk', 'media_s3');
        $objects = ['old/unrelated.webp' => 'old bytes'];
        $attempts = [];
        $deletes = [];
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->times(4)->andReturnFalse();
        $disk->shouldReceive('put')->times($failure)->andReturnUsing(function ($key, $bytes) use (&$objects, &$attempts, $failure, $throws) {
            $attempts[] = $key;
            $objects[$key] = $bytes;
            if (count($attempts) === $failure) {
                if ($throws) {
                    throw new RuntimeException('secret bucket/key');
                }

                return false;
            }

            return true;
        });
        $disk->shouldReceive('delete')->times($failure)->andReturnUsing(function ($key) use (&$objects, &$deletes) {
            $deletes[] = $key;
            unset($objects[$key]);

            return true;
        });
        try {
            $this->service($disk)->store($this->prepared());
            $this->fail('Failed write was accepted.');
        } catch (MediaStorageException $exception) {
            $this->assertSame('No se pudo almacenar el conjunto multimedia.', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
            $this->assertStringNotContainsString('secret', (string) $exception);
        }
        $this->assertSame(array_reverse($attempts), $deletes);
        $this->assertSame(['old/unrelated.webp' => 'old bytes'], $objects);
    }

    public static function writeFailures(): iterable
    {
        foreach ([1, 2, 3, 4] as $index) {
            yield 'false '.$index => [$index, false];
            yield 'throw '.$index => [$index, true];
        }
    }

    public function test_cleanup_failure_logs_safely_and_continues_without_masking_original_failure(): void
    {
        config()->set('media.disk', 'media_s3');
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->times(4)->andReturnFalse();
        $disk->shouldReceive('put')->once()->ordered()->andReturnTrue();
        $disk->shouldReceive('put')->once()->ordered()->andThrow(new RuntimeException('private secret write'));
        $disk->shouldReceive('delete')->once()->ordered()->andThrow(new RuntimeException('private secret delete'));
        $disk->shouldReceive('delete')->once()->ordered()->andReturnFalse();
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('warning')->twice()->with('Responsive media operation failed.', ['operation' => 'cleanup']);
        $this->expectException(MediaStorageException::class);
        $this->expectExceptionMessage('No se pudo almacenar el conjunto multimedia.');
        $this->service($disk, $logger)->store($this->prepared());
    }

    public function test_collision_does_not_write_or_delete_existing_objects(): void
    {
        config()->set('media.disk', 'media_s3');
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->once()->andReturnTrue();
        $disk->shouldNotReceive('put');
        $disk->shouldNotReceive('delete');
        $this->expectException(MediaStorageException::class);
        $this->service($disk)->store($this->prepared());
    }

    #[DataProvider('readFailures')]
    public function test_corrupt_or_transient_reads_fall_back_without_leaking_or_accessing_declared_paths(string $case): void
    {
        config()->set('media.disk', 'media_s3');
        $disk = Mockery::mock(FilesystemAdapter::class);
        $key = app(ResponsiveMediaKeys::class)->manifest(self::MASTER, VariantPolicyVersion::V1);
        $stream = null;
        if ($case === 'exists') {
            $disk->shouldReceive('exists')->once()->with($key)->andThrow(new RuntimeException('secret bucket'));
        } else {
            $disk->shouldReceive('exists')->once()->with($key)->andReturnTrue();
            if ($case === 'read') {
                $disk->shouldReceive('readStream')->once()->with($key)->andThrow(new RuntimeException('secret key'));
            } elseif ($case === 'false') {
                $disk->shouldReceive('readStream')->once()->with($key)->andReturnFalse();
            } else {
                $stream = fopen('php://temp', 'w+b');
                $json = match ($case) {
                    'large' => str_repeat(' ', ResponsiveManifest::MAX_BYTES + 1),
                    'identity' => str_replace('banners/', 'avatars/', ResponsiveManifest::fromPrepared(self::MASTER, $this->prepared(), app(ResponsiveMediaKeys::class))->toJson()),
                    default => '{"master":{"key":"../../secret"}}',
                };
                fwrite($stream, $json);
                rewind($stream);
                $disk->shouldReceive('readStream')->once()->with($key)->andReturn($stream);
            }
        }
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('warning')->once()->with('Responsive media operation failed.', ['operation' => 'read_manifest']);
        $this->assertNull($this->service($disk, $logger)->readManifest(self::MASTER));
        if ($stream !== null) {
            $this->assertFalse(is_resource($stream));
        }
    }

    public static function readFailures(): iterable
    {
        foreach (['exists', 'read', 'false', 'large', 'identity', 'corrupt'] as $case) {
            yield [$case];
        }
    }

    public function test_invalid_master_is_rejected_before_accessing_storage(): void
    {
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldNotReceive('exists');
        $this->expectException(MediaStorageException::class);
        $this->service($disk)->readManifest('../private');
    }

    #[DataProvider('legacyFormats')]
    public function test_legacy_normalize_store_stays_single_master_and_delivery_rejects_manifest(string $format, string $extension): void
    {
        Storage::fake('media_local');
        config()->set('media.disk', 'media_local');
        $image = app(ImageNormalizer::class)->normalize($this->uploadBytes($this->fixtureBytes(800, 400, $format)), 'banner');
        $key = app(MediaStorageService::class)->store(MediaPurpose::Banner, $image);
        $this->assertSame($extension, $image->extension);
        $this->assertSame([$key], Storage::disk('media_local')->allFiles());
        $this->assertNull(app(ResponsiveMediaStorage::class)->readManifest($key));
        $this->assertSame(200, app(MediaDeliveryService::class)->deliverPublic($key)->getStatusCode());
        $this->expectException(MediaStorageException::class);
        app(MediaDeliveryService::class)->deliverPublic(app(ResponsiveMediaKeys::class)->manifest($key, VariantPolicyVersion::V1));
    }

    public static function legacyFormats(): iterable
    {
        yield ['jpeg', 'jpg'];
        yield ['png', 'png'];
        yield ['webp', 'webp'];
    }

    private function prepared(): PreparedResponsiveSet
    {
        return app(ResponsiveImagePreparer::class)->prepare(
            $this->uploadBytes($this->fixtureBytes()), ResponsiveImageProfile::Banner, ImagePreparationPolicy::Photo,
        );
    }

    private function service(FilesystemAdapter $disk, ?LoggerInterface $logger = null): ResponsiveMediaStorage
    {
        $manager = Mockery::mock(FilesystemManager::class);
        $manager->shouldReceive('disk')->with(config('media.disk'))->andReturn($disk);

        return new ResponsiveMediaStorage($manager, new MediaObjectKeyGenerator, app(ResponsiveMediaKeys::class),
            $logger ?? Mockery::mock(LoggerInterface::class));
    }
}
