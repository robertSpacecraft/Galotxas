<?php

namespace Tests\Unit\Media;

use GdImage;
use Illuminate\Http\UploadedFile;

trait ResponsiveImageFixtures
{
    /** @var list<string> */
    private array $imageFixturePaths = [];

    private function uploadBytes(string $bytes, string $name = 'untrusted.exe'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'galotxas-responsive-test-');
        file_put_contents($path, $bytes);
        $this->imageFixturePaths[] = $path;

        return new UploadedFile($path, $name, null, null, true);
    }

    private function fixtureBytes(int $width = 800, int $height = 400, string $format = 'png', bool $transparent = false): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorallocatealpha($image, ($x * 7) % 256, ($y * 11) % 256,
                    ($x + $y * 3) % 256, $transparent ? ($x + $y) % 128 : 0);
                imagesetpixel($image, $x, $y, $color);
            }
        }
        ob_start();
        match ($format) {
            'png' => imagepng($image),
            'jpeg' => imagejpeg($image, null, 90),
            'webp' => imagewebp($image, null, IMG_WEBP_LOSSLESS),
        };
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    private function orientedJpeg(int $width, int $height): string
    {
        $jpeg = $this->fixtureBytes($width, $height, 'jpeg');
        $tiff = "II\x2A\x00\x08\x00\x00\x00\x01\x00"
            ."\x12\x01\x03\x00\x01\x00\x00\x00\x06\x00\x00\x00\x00\x00\x00\x00";
        $exif = "Exif\0\0".$tiff;
        $comment = 'PRIVATE_RESPONSIVE_METADATA';

        return substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif
            ."\xFF\xFE".pack('n', strlen($comment) + 2).$comment.substr($jpeg, 2);
    }

    /** Compare every pixel, ignoring only the invisible RGB of fully transparent pixels. */
    private function pixelDigests(GdImage $image): array
    {
        $alpha = hash_init('sha256');
        $rgb = hash_init('sha256');
        for ($y = 0; $y < imagesy($image); $y++) {
            for ($x = 0; $x < imagesx($image); $x++) {
                $color = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                hash_update($alpha, chr($color['alpha']));
                if ($color['alpha'] < 127) {
                    hash_update($rgb, pack('CCC', $color['red'], $color['green'], $color['blue']));
                }
            }
        }

        return ['alpha' => hash_final($alpha), 'visible_rgb' => hash_final($rgb)];
    }

    protected function tearDown(): void
    {
        foreach ($this->imageFixturePaths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }
}
