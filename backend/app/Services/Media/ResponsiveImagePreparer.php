<?php

namespace App\Services\Media;

use App\Services\Media\Exceptions\InvalidMediaImage;
use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageManagerInterface;
use Throwable;

class ResponsiveImagePreparer
{
    private readonly ImageManagerInterface $manager;

    public function __construct(?ImageManagerInterface $manager = null, private readonly ImageDerivativeEncoder $encoder = new ImageDerivativeEncoder)
    {
        $this->manager = $manager ?? ImageManager::gd(
            autoOrientation: true,
            decodeAnimation: false,
            strip: true,
        );
    }

    public function prepare(
        UploadedFile $file,
        ResponsiveImageProfile $profile,
        ImagePreparationPolicy $policy,
    ): PreparedResponsiveSet {
        $version = VariantPolicyVersion::V1;
        $widths = $version->widths($profile);
        $validator = new ImageInputValidator;
        $limits = $validator->profile($profile->value);
        $input = $validator->validate($file, $limits);

        try {
            // One source decode, including orientation. Every candidate uses these pixels.
            $source = $this->manager->read($input['bytes']);
            $masterImage = clone $source;
            $masterImage->scaleDown(width: $limits['output_max_width'], height: $limits['output_max_height']);
            $master = $this->encoder->encode($masterImage, $policy);
            unset($masterImage);

            $variants = [];
            foreach ($widths as $width) {
                if ($width >= $master->width) {
                    continue;
                }
                $candidate = clone $source;
                $candidate->scaleDown(width: $width);
                $variants[] = $this->encoder->encode($candidate, $policy);
                unset($candidate);
            }

            return new PreparedResponsiveSet($master, $variants, $profile, $policy, $version);
        } catch (Throwable $exception) {
            throw new InvalidMediaImage('La imagen no se pudo decodificar y normalizar.', previous: $exception);
        }
    }
}
