<?php

namespace App\Services\Media\Backfill\Safety;

enum ObjectKind: string
{
    case Variant = 'variant';
    case Manifest = 'manifest';
}
