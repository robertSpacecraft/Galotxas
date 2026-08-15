<?php

namespace App\Services\Media;

enum MediaPurpose: string
{
    case Avatar = 'avatars';
    case Banner = 'banners';
    case News = 'news';
    case Cms = 'cms';
}
