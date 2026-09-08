<?php

namespace App\Services\Media;

use InvalidArgumentException;

enum VariantPolicyVersion: string
{
    case V1 = 'v1';

    /** @return list<int> */
    public function widths(ResponsiveImageProfile $profile): array
    {
        $widths = config("media.variant_policies.{$this->value}.widths.{$profile->value}");

        if (! is_array($widths) || ! array_is_list($widths) || count($widths) > 5) {
            throw new InvalidArgumentException('La política de variantes no es válida.');
        }

        $previous = 0;
        foreach ($widths as $width) {
            if (! is_int($width) || $width <= $previous || $width > 2048) {
                throw new InvalidArgumentException('La política de variantes no es válida.');
            }
            $previous = $width;
        }

        return $widths;
    }
}
