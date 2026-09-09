<?php

namespace Tests\Unit\Media;

use App\Services\Media\Backfill\InspectionReason;
use App\Services\Media\Exceptions\InvalidStoredMaster;
use App\Services\Media\ExistingMasterPreparer;
use App\Services\Media\ImageDerivativeEncoder;
use App\Services\Media\ImagePreparationPolicy;
use App\Services\Media\ResponsiveImageProfile;
use App\Services\Media\ResponsiveMediaKeys;
use Intervention\Image\EncodedImage;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ImageManagerInterface;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExistingMasterPreparerTest extends TestCase
{
    use ResponsiveImageFixtures;

    private const UUID = '550e8400-e29b-41d4-a716-446655440000';

    #[DataProvider('formats')]
    public function test_existing_master_is_preserved_and_only_derivatives_are_encoded(string $format, string $ext, ImagePreparationPolicy $policy): void
    {
        $bytes = $this->fixtureBytes(400, 200, $format);
        $key = 'banners/'.self::UUID.'.'.$ext;
        $real = ImageManager::gd(autoOrientation: false, decodeAnimation: false, strip: true);
        $manager = Mockery::mock(ImageManagerInterface::class);
        $manager->shouldReceive('read')->once()->with($bytes)->andReturnUsing(fn () => $real->read($bytes));
        $encoder = Mockery::mock(ImageDerivativeEncoder::class);
        $encoder->shouldReceive('encode')->once()->withArgs(fn ($image, $actualPolicy) => $image->width() === 320 && $actualPolicy === $policy)
            ->andReturnUsing(fn ($image, $actualPolicy) => (new ImageDerivativeEncoder)->encode($image, $actualPolicy));
        $prepared = (new ExistingMasterPreparer(app(ResponsiveMediaKeys::class), $encoder, $manager))
            ->prepare($key, $bytes, ResponsiveImageProfile::Banner, $policy);
        $this->assertSame($key, $prepared->master->key);
        $this->assertSame(hash('sha256', $bytes), $prepared->masterSha256);
        $this->assertSame(strlen($bytes), $prepared->master->size);
        $this->assertSame([320], array_column($prepared->variants, 'width'));
        $this->assertFalse(property_exists($prepared->master, 'bytes'));
        $this->assertSame(2, $prepared->manifest->schemaVersion);
        $candidate = $real->read($bytes)->scaleDown(width: 320);
        if ($policy === ImagePreparationPolicy::Photo) {
            $expected = (string) $candidate->toWebp(quality: 82, strip: true);
        } else {
            $png = (string) $candidate->toPng(interlaced: false, indexed: false);
            $webp = (string) $candidate->toWebp(quality: 100, strip: true);
            $expected = strlen($webp) < strlen($png) ? $webp : $png;
        }
        $this->assertSame($expected, $prepared->variants[0]->bytes);
        $this->assertStringNotContainsString('Exif', $prepared->variants[0]->bytes);
    }

    public static function formats(): iterable
    {
        foreach ([ImagePreparationPolicy::Photo, ImagePreparationPolicy::Graphic] as $policy) {
            foreach (['jpeg' => 'jpg', 'png' => 'png', 'webp' => 'webp'] as $format => $ext) {
                yield [$format, $ext, $policy];
            }
        }
    }

    public function test_graphic_tie_selects_png(): void
    {
        $png = $this->fixtureBytes(20, 10, 'png');
        $webp = $this->fixtureBytes(20, 10, 'webp');
        $length = max(strlen($png), strlen($webp));
        $png = str_pad($png, $length, "\0");
        $webp = str_pad($webp, $length, "\0");
        $image = Mockery::mock(ImageInterface::class);
        $image->shouldReceive('width')->andReturn(20);
        $image->shouldReceive('height')->andReturn(10);
        $image->shouldReceive('toPng')->once()->andReturn(new EncodedImage($png));
        $image->shouldReceive('toWebp')->once()->andReturn(new EncodedImage($webp));
        $this->assertSame($png, (new ImageDerivativeEncoder)->encode($image, ImagePreparationPolicy::Graphic)->bytes);
    }

    public function test_small_master_has_no_derivatives_and_http_limit_does_not_apply(): void
    {
        config()->set('media.profiles.avatar.input_max_kb', 0);
        $encoder = Mockery::mock(ImageDerivativeEncoder::class);
        $encoder->shouldNotReceive('encode');
        $result = (new ExistingMasterPreparer(app(ResponsiveMediaKeys::class), $encoder))->prepare(
            'avatars/'.self::UUID.'.png', $this->fixtureBytes(128, 64), ResponsiveImageProfile::Avatar, ImagePreparationPolicy::Photo);
        $this->assertSame([], $result->variants);
    }

    public function test_decode_and_encoder_failures_have_different_typed_reasons(): void
    {
        $bytes = $this->fixtureBytes(400, 200);
        $key = 'banners/'.self::UUID.'.png';
        $manager = Mockery::mock(ImageManagerInterface::class);
        $manager->shouldReceive('read')->once()->andThrow(new \RuntimeException('decoder failure'));
        try {
            (new ExistingMasterPreparer(app(ResponsiveMediaKeys::class), new ImageDerivativeEncoder, $manager))
                ->prepare($key, $bytes, ResponsiveImageProfile::Banner, ImagePreparationPolicy::Photo);
            $this->fail('Decode failure was ignored.');
        } catch (InvalidStoredMaster $exception) {
            $this->assertSame(InspectionReason::InvalidImage, $exception->reason);
        }
        $encoder = Mockery::mock(ImageDerivativeEncoder::class);
        $encoder->shouldReceive('encode')->once()->andThrow(new \RuntimeException('encoder failure'));
        try {
            (new ExistingMasterPreparer(app(ResponsiveMediaKeys::class), $encoder))
                ->prepare($key, $bytes, ResponsiveImageProfile::Banner, ImagePreparationPolicy::Photo);
            $this->fail('Encoding failure was ignored.');
        } catch (InvalidStoredMaster $exception) {
            $this->assertSame(InspectionReason::EncodingFailed, $exception->reason);
        }
    }

    #[DataProvider('unsafeCases')]
    public function test_unsafe_inputs_are_typed(string $case, InspectionReason $reason): void
    {
        $bytes = $this->fixtureBytes(40, 20);
        $ext = 'png';
        if ($case === 'size') {
            $this->assertSame(32 * 1024 * 1024, config('media.stored_master_max_bytes'));
            $bytes = str_repeat('x', 32 * 1024 * 1024 + 1);
        } elseif ($case === 'invalid') {
            $bytes = 'not an image';
        } elseif ($case === 'bounds') {
            $bytes = $this->fixtureBytes(513, 1);
        } elseif ($case === 'mime') {
            $ext = 'jpg';
        } elseif ($case === 'orientation') {
            $bytes = $this->orientedJpeg(40, 20);
            $ext = 'jpg';
        } elseif ($case === 'png_exif') {
            $chunk = 'eXIf'.'metadata';
            $bytes = substr($bytes, 0, 33).pack('N', 8).$chunk.pack('N', crc32($chunk)).substr($bytes, 33);
        } elseif ($case === 'webp_exif') {
            $ext = 'webp';
            $bytes = $this->fixtureBytes(40, 20, 'webp');
            $bytes .= 'EXIF'.pack('V', 8).'metadata';
            $bytes = substr_replace($bytes, pack('V', strlen($bytes) - 8), 4, 4);
        }
        try {
            app(ExistingMasterPreparer::class)->prepare('avatars/'.self::UUID.'.'.$ext, $bytes, ResponsiveImageProfile::Avatar, ImagePreparationPolicy::Photo);
            $this->fail('Unsafe master was accepted.');
        } catch (InvalidStoredMaster $exception) {
            $this->assertSame($reason, $exception->reason);
        }
    }

    public static function unsafeCases(): iterable
    {
        yield ['size', InspectionReason::UnsafeMaster];
        yield ['invalid', InspectionReason::InvalidImage];
        yield ['bounds', InspectionReason::UnsafeMaster];
        yield ['mime', InspectionReason::InvalidImage];
        yield ['orientation', InspectionReason::UnexpectedOrientation];
        yield ['png_exif', InspectionReason::UnsupportedMetadata];
        yield ['webp_exif', InspectionReason::UnsupportedMetadata];
    }

    #[DataProvider('profileBounds')]
    public function test_each_historical_profile_bound_is_enforced(ResponsiveImageProfile $profile, int $width, int $height): void
    {
        $this->expectException(InvalidStoredMaster::class);
        app(ExistingMasterPreparer::class)->prepare($profile->purpose()->value.'/'.self::UUID.'.png',
            $this->fixtureBytes($width, $height), $profile, ImagePreparationPolicy::Photo);
    }

    public static function profileBounds(): iterable
    {
        yield [ResponsiveImageProfile::Avatar, 1, 513];
        yield [ResponsiveImageProfile::NewsCover, 1921, 1];
        yield [ResponsiveImageProfile::NewsCover, 1, 1081];
        yield [ResponsiveImageProfile::SponsorLogo, 1201, 1];
        yield [ResponsiveImageProfile::SponsorLogo, 1, 601];
        yield [ResponsiveImageProfile::Banner, 1921, 1];
        yield [ResponsiveImageProfile::Banner, 1, 1921];
    }
}
