<?php

namespace App\Services\Media\Backfill;

use App\Models\Category;
use App\Models\Championship;
use App\Models\NewsArticle;
use App\Models\Season;
use App\Models\Sponsor;
use App\Models\User;
use App\Services\Media\ImagePreparationPolicy;
use App\Services\Media\ResponsiveImageProfile;
use Illuminate\Database\Eloquent\Model;

enum ManagedMediaDomain: string
{
    case Avatar = 'avatar';
    case News = 'news';
    case Sponsor = 'sponsor';
    case Season = 'season';
    case Championship = 'championship';
    case Category = 'category';

    /** @return class-string<Model> */
    public function model(): string
    {
        return match ($this) {
            self::Avatar => User::class, self::News => NewsArticle::class, self::Sponsor => Sponsor::class,
            self::Season => Season::class, self::Championship => Championship::class, self::Category => Category::class,
        };
    }

    public function column(): string
    {
        return match ($this) {
            self::Avatar => 'profile_photo_path', self::News => 'image_key', self::Sponsor => 'logo_key',
            default => 'image_path',
        };
    }

    public function profile(): ResponsiveImageProfile
    {
        return match ($this) {
            self::Avatar => ResponsiveImageProfile::Avatar, self::News => ResponsiveImageProfile::NewsCover,
            self::Sponsor => ResponsiveImageProfile::SponsorLogo, default => ResponsiveImageProfile::Banner,
        };
    }

    public function policy(): ImagePreparationPolicy
    {
        return $this === self::Sponsor ? ImagePreparationPolicy::Graphic : ImagePreparationPolicy::Photo;
    }

    public function nullable(): bool
    {
        return $this !== self::Sponsor;
    }

    public function metadataPrefix(): ?string
    {
        return match ($this) {
            self::News => 'image', self::Sponsor => 'logo', default => null,
        };
    }
}
