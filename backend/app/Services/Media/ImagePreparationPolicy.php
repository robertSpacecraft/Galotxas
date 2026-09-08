<?php

namespace App\Services\Media;

enum ImagePreparationPolicy: string
{
    case Photo = 'photo';
    case Graphic = 'graphic';
}
