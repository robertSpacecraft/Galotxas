<?php

namespace App\Services\Media;

enum ResponsiveImageProfile: string
{
    case Avatar = 'avatar';
    case Banner = 'banner';
    case NewsCover = 'news_cover';
    case SponsorLogo = 'sponsor_logo';
    case Content = 'content';

    /** Storage namespace only; encoding policy remains an explicit caller choice. */
    public function purpose(): MediaPurpose
    {
        return match ($this) {
            self::Avatar => MediaPurpose::Avatar,
            self::Banner => MediaPurpose::Banner,
            self::NewsCover => MediaPurpose::News,
            self::SponsorLogo => MediaPurpose::Sponsor,
            self::Content => MediaPurpose::Cms,
        };
    }
}
