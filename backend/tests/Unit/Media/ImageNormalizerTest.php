<?php

namespace Tests\Unit\Media;

use App\Services\Media\Exceptions\InvalidMediaImage;
use App\Services\Media\ImageNormalizer;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ImageNormalizerTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    #[DataProvider('validImageProvider')]
    public function test_it_normalizes_supported_formats_without_trusting_the_filename(
        string $format,
        string $expectedMime,
        string $expectedExtension,
    ): void {
        $upload = $this->upload($this->imageBytes($format, 80, 40), 'untrusted-name.exe');

        $result = app(ImageNormalizer::class)->normalize($upload, 'content');
        $decoded = getimagesizefromstring($result->bytes);

        $this->assertSame($expectedMime, $result->mimeType);
        $this->assertSame($expectedExtension, $result->extension);
        $this->assertSame(80, $result->width);
        $this->assertSame(40, $result->height);
        $this->assertSame(strlen($result->bytes), $result->size);
        $this->assertIsArray($decoded);
        $this->assertSame($expectedMime, $decoded['mime']);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function validImageProvider(): iterable
    {
        yield 'JPEG' => ['jpeg', 'image/jpeg', 'jpg'];
        yield 'PNG' => ['png', 'image/png', 'png'];
        yield 'WebP' => ['webp', 'image/webp', 'webp'];
    }

    #[DataProvider('unsupportedImageProvider')]
    public function test_it_rejects_unsupported_or_invalid_formats(string $bytes, string $filename): void
    {
        $this->expectException(InvalidMediaImage::class);

        app(ImageNormalizer::class)->normalize($this->upload($bytes, $filename), 'content');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unsupportedImageProvider(): iterable
    {
        yield 'text renamed as JPEG' => ['plain text', 'renamed.jpg'];
        yield 'SVG' => ['<svg xmlns="http://www.w3.org/2000/svg"></svg>', 'vector.svg'];
        yield 'animated GIF' => [self::animatedGifBytes(), 'animation.gif'];
        yield 'AVIF' => [pack('N', 24).'ftypavif'.pack('N', 0).'avifmif1', 'image.avif'];
    }

    public function test_it_rejects_content_that_claims_to_be_jpeg_but_cannot_be_decoded(): void
    {
        $upload = UploadedFile::fake()->createWithContent('forged.jpg', 'not a jpeg');

        $this->expectException(InvalidMediaImage::class);
        $this->expectExceptionMessage('contenido no es una imagen válida');

        app(ImageNormalizer::class)->normalize($upload, 'avatar');
    }

    public function test_it_enforces_the_input_byte_limit(): void
    {
        config()->set('media.profiles.avatar.input_max_kb', 0);

        $this->expectException(InvalidMediaImage::class);
        $this->expectExceptionMessage('tamaño máximo');

        app(ImageNormalizer::class)->normalize(
            $this->upload($this->imageBytes('jpeg', 10, 10), 'photo.jpg'),
            'avatar',
        );
    }

    public function test_it_enforces_dimension_and_pixel_limits_before_decoding(): void
    {
        $normalizer = app(ImageNormalizer::class);
        config()->set('media.profiles.avatar.max_width', 10);

        try {
            $normalizer->normalize(
                $this->upload($this->imageBytes('png', 11, 5), 'wide.png'),
                'avatar',
            );
            $this->fail('An oversized dimension was accepted.');
        } catch (InvalidMediaImage $exception) {
            $this->assertStringContainsString('dimensiones', $exception->getMessage());
        }

        config()->set('media.profiles.avatar.max_width', 4096);
        config()->set('media.profiles.avatar.max_pixels', 100);

        $this->expectException(InvalidMediaImage::class);
        $this->expectExceptionMessage('máximo de píxeles');

        $normalizer->normalize(
            $this->upload($this->imageBytes('png', 11, 10), 'too-many-pixels.png'),
            'avatar',
        );
    }

    public function test_it_scales_down_preserving_ratio_and_never_upscales(): void
    {
        config()->set('media.profiles.avatar.output_max_width', 50);
        config()->set('media.profiles.avatar.output_max_height', 50);
        $normalizer = app(ImageNormalizer::class);

        $large = $normalizer->normalize(
            $this->upload($this->imageBytes('jpeg', 120, 60), 'large.jpg'),
            'avatar',
        );
        $small = $normalizer->normalize(
            $this->upload($this->imageBytes('jpeg', 20, 10), 'small.jpg'),
            'avatar',
        );

        $this->assertSame([50, 25], [$large->width, $large->height]);
        $this->assertSame([20, 10], [$small->width, $small->height]);
    }

    public function test_it_applies_exif_orientation_and_strips_metadata(): void
    {
        $metadataMarker = 'GALOTXAS_PRIVATE_METADATA';
        $jpeg = $this->jpegWithOrientationAndComment(40, 20, 6, $metadataMarker);

        $result = app(ImageNormalizer::class)->normalize(
            $this->upload($jpeg, 'orientation.jpg'),
            'avatar',
        );

        $this->assertSame([20, 40], [$result->width, $result->height]);
        $this->assertStringNotContainsString("Exif\0\0", $result->bytes);
        $this->assertStringNotContainsString($metadataMarker, $result->bytes);
        $this->assertIsArray(getimagesizefromstring($result->bytes));
    }

    public function test_unknown_profile_is_rejected(): void
    {
        $this->expectException(InvalidMediaImage::class);
        $this->expectExceptionMessage('perfil');

        app(ImageNormalizer::class)->normalize(
            $this->upload($this->imageBytes('jpeg', 10, 10), 'photo.jpg'),
            'unknown',
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    private function upload(string $bytes, string $clientName): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'galotxas-media-test-');

        if ($path === false) {
            $this->fail('Could not create a temporary test file.');
        }

        file_put_contents($path, $bytes);
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $clientName, null, null, true);
    }

    private function imageBytes(string $format, int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $background = imagecolorallocate($image, 32, 96, 160);
        imagefill($image, 0, 0, $background);
        ob_start();

        match ($format) {
            'jpeg' => imagejpeg($image, null, 90),
            'png' => imagepng($image),
            'webp' => imagewebp($image, null, 90),
        };

        $bytes = ob_get_clean();
        imagedestroy($image);

        if (! is_string($bytes)) {
            $this->fail('Could not encode a test image.');
        }

        return $bytes;
    }

    private function jpegWithOrientationAndComment(
        int $width,
        int $height,
        int $orientation,
        string $comment,
    ): string {
        $jpeg = $this->imageBytes('jpeg', $width, $height);
        $tiff = "II\x2A\x00\x08\x00\x00\x00"
            ."\x01\x00"
            ."\x12\x01\x03\x00\x01\x00\x00\x00"
            .pack('v', $orientation)."\x00\x00"
            ."\x00\x00\x00\x00";
        $exif = "Exif\0\0".$tiff;
        $exifSegment = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;
        $commentSegment = "\xFF\xFE".pack('n', strlen($comment) + 2).$comment;

        return substr($jpeg, 0, 2).$exifSegment.$commentSegment.substr($jpeg, 2);
    }

    private static function animatedGifBytes(): string
    {
        return "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xFF\xFF\xFF"
            ."\x21\xFF\x0BNETSCAPE2.0\x03\x01\x00\x00\x00"
            ."\x21\xF9\x04\x00\x01\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00"
            ."\x21\xF9\x04\x00\x01\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B";
    }
}
