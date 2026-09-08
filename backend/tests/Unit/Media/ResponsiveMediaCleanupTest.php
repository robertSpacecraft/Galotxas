<?php

namespace Tests\Unit\Media;

use App\Services\Media\ImageFormat;
use App\Services\Media\ImagePreparationPolicy;
use App\Services\Media\MediaObjectKeyGenerator;
use App\Services\Media\NormalizedImage;
use App\Services\Media\PreparedResponsiveSet;
use App\Services\Media\ResponsiveImagePreparer;
use App\Services\Media\ResponsiveImageProfile;
use App\Services\Media\ResponsiveMediaKeys;
use App\Services\Media\ResponsiveMediaStorage;
use App\Services\Media\VariantPolicyVersion;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\TestCase;

class ResponsiveMediaCleanupTest extends TestCase
{
    use ResponsiveImageFixtures;

    #[DataProvider('sets')]
    public function test_cleanup_is_complete_bounded_and_independent_of_manifest_contents(string $case, ResponsiveImageProfile $profile): void
    {
        config()->set('media.disk', 'media_local');
        $disk = Storage::fake('media_local');
        $storage = app(ResponsiveMediaStorage::class);
        $set = $storage->store(app(ResponsiveImagePreparer::class)->prepare(
            $this->uploadBytes($this->fixtureBytes($case === 'zero' ? 80 : 800, 40)),
            $profile, $profile === ResponsiveImageProfile::SponsorLogo ? ImagePreparationPolicy::Graphic : ImagePreparationPolicy::Photo,
        ));
        $owned = $this->owned($set->masterKey, $profile);
        $foreign = 'variants/v1/news/00000000-0000-4000-8000-000000000001/w320.webp';
        $unknown = str_replace('manifest.json', 'w321.webp', $set->manifestKey);
        $disk->put($foreign, 'foreign');
        $disk->put($unknown, 'unknown width');
        if ($case === 'corrupt') {
            $disk->put($set->manifestKey, '{broken');
        } elseif ($case === 'hostile') {
            $disk->put($set->manifestKey, json_encode(['variants' => ['../../private', $foreign, $unknown]]));
        } elseif ($case === 'missing' || $case === 'partial') {
            $disk->delete($set->manifestKey);
            if ($case === 'partial') {
                $disk->delete($set->masterKey);
            }
        } elseif ($case === 'graphic') {
            // A partial/replaced candidate can exist in either lossless format.
            foreach (array_slice($owned, 1, -1) as $key) {
                $disk->put($key, 'partial graphic candidate');
            }
        }

        $deleted = [];
        $adapter = Mockery::mock(FilesystemAdapter::class);
        $adapter->shouldReceive('delete')->times(count($owned) * 2)->andReturnUsing(function ($key) use ($disk, &$deleted) {
            $deleted[] = $key;

            return $disk->delete($key);
        });
        // No exists, readStream, get, listing or directory deletion is authorized.
        $service = $this->service($adapter);
        $service->deleteSet($set->masterKey, $profile);
        $service->deleteSet($set->masterKey, $profile);
        $this->assertSame([...$owned, ...$owned], $deleted);
        $this->assertEqualsCanonicalizing([$foreign, $unknown], $disk->allFiles());
    }

    public static function sets(): iterable
    {
        foreach (['photo', 'zero', 'missing', 'corrupt', 'hostile', 'partial'] as $case) {
            yield $case => [$case, ResponsiveImageProfile::Banner];
        }
        yield 'graphic both formats' => ['graphic', ResponsiveImageProfile::SponsorLogo];
    }

    public function test_valid_graphic_manifest_with_mixed_png_and_webp_variants_is_completely_removed(): void
    {
        config()->set('media.disk', 'media_local');
        $disk = Storage::fake('media_local');
        $prepared = app(ResponsiveImagePreparer::class)->prepare(
            $this->uploadBytes($this->fixtureBytes(800, 400)), ResponsiveImageProfile::SponsorLogo, ImagePreparationPolicy::Graphic,
        );
        $variants = [];
        foreach ($prepared->variants as $index => $variant) {
            $image = ImageManager::gd(strip: true)->read($variant->bytes);
            $format = $index % 2 === 0 ? ImageFormat::Png : ImageFormat::Webp;
            $bytes = (string) ($format === ImageFormat::Png ? $image->toPng() : $image->toWebp(quality: 100, strip: true));
            $variants[] = new NormalizedImage($bytes, $format->mimeType(), $format->value, $variant->width, $variant->height, strlen($bytes));
        }
        $storage = app(ResponsiveMediaStorage::class);
        $stored = $storage->store(new PreparedResponsiveSet($prepared->master, $variants, $prepared->profile, $prepared->policy, $prepared->version));
        $this->assertSame(['image/png', 'image/webp'], array_values(array_unique(array_column($storage->readManifest($stored->masterKey)->variants, 'mimeType'))));
        $storage->deleteSet($stored->masterKey, ResponsiveImageProfile::SponsorLogo);
        $this->assertSame([], $disk->allFiles());
    }

    #[DataProvider('legacyFormats')]
    public function test_legacy_master_only_is_normal_and_removed(string $extension): void
    {
        config()->set('media.disk', 'media_local');
        $disk = Storage::fake('media_local');
        $key = 'avatars/00000000-0000-4000-8000-000000000001.'.$extension;
        $disk->put($key, 'legacy');
        app(ResponsiveMediaStorage::class)->deleteSet($key, ResponsiveImageProfile::Avatar);
        $this->assertSame([], $disk->allFiles());
    }

    public static function legacyFormats(): iterable
    {
        foreach (['jpg', 'png', 'webp'] as $format) {
            yield [$format];
        }
    }

    #[DataProvider('invalidKeys')]
    public function test_invalid_references_never_resolve_a_disk(mixed $key): void
    {
        $manager = Mockery::mock(FilesystemManager::class);
        $manager->shouldNotReceive('disk');
        $logger = Mockery::mock(LoggerInterface::class);
        if ($key !== null) {
            $logger->shouldReceive('warning')->once()->with('Responsive media operation failed.', ['operation' => 'skip_invalid_reference']);
        }
        (new ResponsiveMediaStorage($manager, new MediaObjectKeyGenerator, app(ResponsiveMediaKeys::class), $logger))
            ->deleteSet($key, ResponsiveImageProfile::Avatar);
    }

    public static function invalidKeys(): iterable
    {
        foreach ([null, 123, '', '../private.jpg', 'https://example.test/private.jpg',
            'avatars/not-a-uuid.webp', 'news/00000000-0000-4000-8000-000000000001.webp'] as $key) {
            yield [$key];
        }
    }

    public function test_failed_deletes_and_failed_logging_do_not_stop_remaining_owned_attempts(): void
    {
        $key = 'avatars/00000000-0000-4000-8000-000000000001.webp';
        $owned = $this->owned($key, ResponsiveImageProfile::Avatar);
        $attempted = [];
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('delete')->times(count($owned))->andReturnUsing(function ($key) use (&$attempted) {
            $attempted[] = $key;
            if (count($attempted) === 1) {
                throw new RuntimeException('secret bucket path');
            }

            return count($attempted) !== 2;
        });
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('warning')->twice()->with('Responsive media operation failed.', ['operation' => 'delete_set'])
            ->andThrow(new RuntimeException('logger unavailable'));
        $this->service($disk, $logger)->deleteSet($key, ResponsiveImageProfile::Avatar);
        $this->assertSame($owned, $attempted);
    }

    private function owned(string $master, ResponsiveImageProfile $profile): array
    {
        $keys = app(ResponsiveMediaKeys::class);
        $owned = [$keys->manifest($master, VariantPolicyVersion::V1)];
        foreach (VariantPolicyVersion::V1->widths($profile) as $width) {
            foreach ([ImageFormat::Png, ImageFormat::Webp] as $format) {
                $owned[] = $keys->variant($master, $profile, VariantPolicyVersion::V1, $width, $format);
            }
        }

        return [...$owned, $master];
    }

    private function service(FilesystemAdapter $disk, ?LoggerInterface $logger = null): ResponsiveMediaStorage
    {
        $manager = Mockery::mock(FilesystemManager::class);
        $manager->shouldReceive('disk')->andReturn($disk);

        return new ResponsiveMediaStorage($manager, new MediaObjectKeyGenerator, app(ResponsiveMediaKeys::class),
            $logger ?? Mockery::mock(LoggerInterface::class));
    }
}
