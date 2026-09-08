<?php

namespace Tests\Unit\Media;

use App\Services\Media\Exceptions\InvalidMediaImage;
use App\Services\Media\ImagePreparationPolicy;
use App\Services\Media\ResponsiveImagePreparer;
use App\Services\Media\ResponsiveImageProfile;
use App\Services\Media\VariantPolicyVersion;
use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageManagerInterface;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ResponsiveImagePreparerTest extends TestCase
{
    use ResponsiveImageFixtures;

    #[DataProvider('profileProvider')]
    public function test_profiles_keep_bounded_sorted_candidates_and_master_limits(
        ResponsiveImageProfile $profile, array $dimensions, array $widths,
    ): void {
        $set = app(ResponsiveImagePreparer::class)->prepare(
            $this->uploadBytes($this->fixtureBytes(2400, 1200)), $profile, ImagePreparationPolicy::Photo,
        );

        $this->assertSame($dimensions, [$set->master->width, $set->master->height]);
        $this->assertSame($widths, array_column($set->variants, 'width'));
        $this->assertSame(VariantPolicyVersion::V1, $set->version);
        $this->assertSame($profile, $set->profile);
        foreach ([$set->master, ...$set->variants] as $image) {
            $this->assertSame('image/webp', $image->mimeType);
            $this->assertSame('webp', $image->extension);
            $this->assertSame(intdiv($image->width, 2), $image->height);
            $this->assertSame(strlen($image->bytes), $image->size);
        }
    }

    public static function profileProvider(): iterable
    {
        yield [ResponsiveImageProfile::Avatar, [512, 256], [128, 256]];
        yield [ResponsiveImageProfile::Banner, [1920, 960], [320, 640, 960, 1280]];
        yield [ResponsiveImageProfile::NewsCover, [1920, 960], [320, 640, 960, 1280]];
        yield [ResponsiveImageProfile::SponsorLogo, [1200, 600], [160, 320, 640]];
        yield [ResponsiveImageProfile::Content, [2048, 1024], [320, 640, 960, 1280, 1920]];
    }

    #[DataProvider('policyProvider')]
    public function test_one_source_decode_orientation_metadata_and_no_encoded_master_decode(ImagePreparationPolicy $policy): void
    {
        $bytes = $this->orientedJpeg(400, 800);
        $realManager = ImageManager::gd(autoOrientation: true, decodeAnimation: false, strip: true);
        $manager = Mockery::mock(ImageManagerInterface::class);
        $manager->shouldReceive('read')->once()->with($bytes)->andReturnUsing(fn () => $realManager->read($bytes));

        $set = (new ResponsiveImagePreparer($manager))->prepare(
            $this->uploadBytes($bytes), ResponsiveImageProfile::Avatar, $policy,
        );

        $this->assertSame([512, 256], [$set->master->width, $set->master->height]);
        $this->assertSame([128, 256], array_column($set->variants, 'width'));
        foreach ([$set->master, ...$set->variants] as $image) {
            $this->assertSame(intdiv($image->width, 2), $image->height);
            $this->assertStringNotContainsString('Exif', $image->bytes);
            $this->assertStringNotContainsString('PRIVATE_RESPONSIVE_METADATA', $image->bytes);
        }
    }

    public static function policyProvider(): iterable
    {
        yield [ImagePreparationPolicy::Photo];
        yield [ImagePreparationPolicy::Graphic];
    }

    #[DataProvider('smallProvider')]
    public function test_small_and_exact_candidate_inputs_never_upscale_or_duplicate_master(int $width, array $variants): void
    {
        $set = app(ResponsiveImagePreparer::class)->prepare(
            $this->uploadBytes($this->fixtureBytes($width, 60)), ResponsiveImageProfile::Avatar, ImagePreparationPolicy::Photo,
        );
        $this->assertSame([$width, 60], [$set->master->width, $set->master->height]);
        $this->assertSame($variants, array_column($set->variants, 'width'));
    }

    public static function smallProvider(): iterable
    {
        yield [80, []];
        yield [128, []];
        yield [256, [128]];
    }

    public function test_portrait_is_limited_by_height_without_crop(): void
    {
        $set = app(ResponsiveImagePreparer::class)->prepare(
            $this->uploadBytes($this->fixtureBytes(800, 1600)), ResponsiveImageProfile::NewsCover, ImagePreparationPolicy::Photo,
        );
        $this->assertSame([540, 1080], [$set->master->width, $set->master->height]);
        $this->assertSame([320], array_column($set->variants, 'width'));
        $this->assertSame(640, $set->variants[0]->height);
    }

    #[DataProvider('sourceFormats')]
    public function test_photo_is_webp_82_independent_of_source_format(string $format): void
    {
        $bytes = $this->fixtureBytes(80, 40, $format, true);
        $set = app(ResponsiveImagePreparer::class)->prepare(
            $this->uploadBytes($bytes), ResponsiveImageProfile::Content, ImagePreparationPolicy::Photo,
        );
        $expected = ImageManager::gd(strip: true)->read($bytes)->toWebp(quality: 82, strip: true);
        $this->assertSame((string) $expected, $set->master->bytes);
        if ($format !== 'jpeg') {
            $this->assertSame($this->pixelDigests(imagecreatefromstring($bytes))['alpha'],
                $this->pixelDigests(imagecreatefromstring($set->master->bytes))['alpha']);
        }
    }

    public static function sourceFormats(): iterable
    {
        yield ['png'];
        yield ['jpeg'];
        yield ['webp'];
    }

    public function test_graphic_preserves_every_alpha_and_visible_rgb_pixel_and_selects_smallest_lossless_candidate(): void
    {
        $bytes = $this->fixtureBytes(800, 400, transparent: true);
        $set = app(ResponsiveImagePreparer::class)->prepare(
            $this->uploadBytes($bytes, 'misleading.jpg'), ResponsiveImageProfile::SponsorLogo, ImagePreparationPolicy::Graphic,
        );
        $source = ImageManager::gd(strip: true)->read($bytes);
        $this->assertSame($this->pixelDigests(imagecreatefromstring($bytes)),
            $this->pixelDigests(imagecreatefromstring($set->master->bytes)));

        foreach ([$set->master, ...$set->variants] as $image) {
            $candidate = clone $source;
            $candidate->scaleDown(width: $image->width);
            $png = (string) $candidate->toPng(interlaced: false, indexed: false);
            $webp = (string) $candidate->toWebp(quality: 100, strip: true);
            foreach ([$png, $webp, $image->bytes] as $encoded) {
                $this->assertSame($this->pixelDigests($candidate->core()->native()),
                    $this->pixelDigests(imagecreatefromstring($encoded)));
            }
            $this->assertSame(min(strlen($png), strlen($webp)), $image->size);
            $this->assertSame(strlen($webp) < strlen($png) ? $webp : $png, $image->bytes);
        }
    }

    #[DataProvider('invalidInputs')]
    public function test_admission_rejects_before_any_decode(string $case): void
    {
        $manager = Mockery::mock(ImageManagerInterface::class);
        $manager->shouldNotReceive('read');
        $upload = $this->uploadBytes($this->fixtureBytes(20, 10));
        switch ($case) {
            case 'upload':
                $upload = new UploadedFile($upload->getPathname(), 'x.png', null, UPLOAD_ERR_PARTIAL, true);
                break;
            case 'bytes':
                config()->set('media.profiles.avatar.input_max_kb', 0);
                break;
            case 'width':
                config()->set('media.profiles.avatar.max_width', 19);
                break;
            case 'height':
                config()->set('media.profiles.avatar.max_height', 9);
                break;
            case 'pixels':
                config()->set('media.profiles.avatar.max_pixels', 199);
                break;
            case 'mismatch':
                $upload = UploadedFile::fake()->createWithContent('forged.jpg', $this->fixtureBytes(20, 10));
                break;
            default:
                $upload = $this->uploadBytes(match ($case) {
                    'svg' => '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
                    'gif' => 'GIF89a'.str_repeat("\0", 50),
                    'avif' => pack('N', 24).'ftypavif'.pack('N', 0).'avifmif1',
                    'heic' => pack('N', 24).'ftypheic'.pack('N', 0).'heicmif1',
                    default => 'not an image',
                });
        }

        $this->expectException(InvalidMediaImage::class);
        (new ResponsiveImagePreparer($manager))->prepare($upload, ResponsiveImageProfile::Avatar, ImagePreparationPolicy::Photo);
    }

    public static function invalidInputs(): iterable
    {
        foreach (['upload', 'bytes', 'width', 'height', 'pixels', 'mismatch', 'svg', 'gif', 'avif', 'heic', 'text'] as $case) {
            yield $case => [$case];
        }
    }
}
