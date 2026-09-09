<?php

namespace App\Services\Media;

use App\Services\Media\Backfill\InspectionReason;
use App\Services\Media\Exceptions\InvalidStoredMaster;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageManagerInterface;
use Throwable;

class ExistingMasterPreparer
{
    private readonly ImageManagerInterface $manager;

    public function __construct(
        private readonly ResponsiveMediaKeys $keys,
        private readonly ImageDerivativeEncoder $encoder,
        ?ImageManagerInterface $manager = null,
    ) {
        // Stored masters were already oriented. Never rotate their pixel contract.
        $this->manager = $manager ?? ImageManager::gd(autoOrientation: false, decodeAnimation: false, strip: true);
    }

    public function prepare(string $masterKey, string $bytes, ResponsiveImageProfile $profile, ImagePreparationPolicy $policy): PreparedResponsiveDerivatives
    {
        if (! (new MediaObjectKeyGenerator)->isValidForPurpose($masterKey, $profile->purpose())) {
            throw new InvalidStoredMaster(InspectionReason::InvalidReference);
        }
        $limit = (int) config('media.stored_master_max_bytes');
        if ($limit < 1 || strlen($bytes) > $limit) {
            throw new InvalidStoredMaster(InspectionReason::UnsafeMaster);
        }
        $header = @getimagesizefromstring($bytes);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if ($header === false || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)
            || $header['mime'] !== $mime || ! str_ends_with($masterKey, '.'.ImageFormat::fromMimeType($mime)->value)) {
            throw new InvalidStoredMaster(InspectionReason::InvalidImage);
        }
        $limits = (new ImageInputValidator)->profile($profile->value);
        [$width, $height] = $header;
        $maxPixels = $limits['output_max_width'] * $limits['output_max_height'];
        if ($width < 1 || $height < 1 || $width > $limits['output_max_width'] || $height > $limits['output_max_height']
            || $width > intdiv($maxPixels, $height)) {
            throw new InvalidStoredMaster(InspectionReason::UnsafeMaster);
        }
        $this->rejectUnsupportedMetadata($bytes, $mime);
        try {
            $source = $this->manager->read($bytes);
        } catch (Throwable $exception) {
            throw new InvalidStoredMaster(InspectionReason::InvalidImage, $exception);
        }
        $orientation = $source->exif('IFD0.Orientation');
        if ($orientation !== null && $orientation !== 1 && $orientation !== '1') {
            throw new InvalidStoredMaster(InspectionReason::UnexpectedOrientation);
        }
        if ($source->width() !== $width || $source->height() !== $height) {
            throw new InvalidStoredMaster(InspectionReason::UnsafeMaster);
        }
        $master = ManifestImage::fromObject((object) [
            'key' => $masterKey, 'width' => $width, 'height' => $height, 'mime_type' => $mime, 'size' => strlen($bytes),
        ]);
        $variants = [];
        try {
            foreach (VariantPolicyVersion::V1->widths($profile) as $candidateWidth) {
                if ($candidateWidth >= $width) {
                    continue;
                }
                $candidate = clone $source;
                $candidate->scaleDown(width: $candidateWidth);
                $variants[] = $this->encoder->encode($candidate, $policy);
                unset($candidate);
            }
        } catch (Throwable $exception) {
            throw new InvalidStoredMaster(InspectionReason::EncodingFailed, $exception);
        }

        return new PreparedResponsiveDerivatives($master, hash('sha256', $bytes), $variants, $profile, $policy, $this->keys);
    }

    /** GD/Intervention reads EXIF only for JPEG. Reject actual EXIF chunks in other containers, not byte substrings. */
    private function rejectUnsupportedMetadata(string $bytes, string $mime): void
    {
        if ($mime === 'image/jpeg') {
            if (! function_exists('exif_read_data')) {
                throw new InvalidStoredMaster(InspectionReason::UnsupportedMetadata);
            }

            return;
        }
        $png = $mime === 'image/png';
        $offset = $png ? 8 : 12;
        $length = strlen($bytes);
        while ($offset + 8 <= $length) {
            $type = substr($bytes, $offset + ($png ? 4 : 0), 4);
            $size = unpack($png ? 'N' : 'V', substr($bytes, $offset + ($png ? 0 : 4), 4))[1];
            $chunkLength = 8 + $size + ($png ? 4 : $size % 2);
            if ($chunkLength > $length - $offset) {
                throw new InvalidStoredMaster(InspectionReason::InvalidImage);
            }
            if ($type === ($png ? 'eXIf' : 'EXIF')) {
                throw new InvalidStoredMaster(InspectionReason::UnsupportedMetadata);
            }
            $offset += $chunkLength;
        }
    }
}
