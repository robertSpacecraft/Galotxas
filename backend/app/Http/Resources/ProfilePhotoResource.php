<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Services\Media\MediaObjectKeyGenerator;
use App\Services\Media\MediaPurpose;
use App\Services\Media\ResponsiveImageProfile;
use App\Services\Media\ResponsiveMediaResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfilePhotoResource extends JsonResource
{
    public static function forUser(User $user): ?self
    {
        $key = $user->profile_photo_path;

        if (! is_string($key) || ! app(MediaObjectKeyGenerator::class)->isValidForPurpose(
            $key,
            MediaPurpose::Avatar
        )) {
            return null;
        }

        return new self($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return app(ResponsiveMediaResolver::class)->image(
            $this->profile_photo_path,
            ResponsiveImageProfile::Avatar,
            route('api.v1.me.profile-photo.image'),
            fn (int $width): string => route('api.v1.me.profile-photo.image.variant', ['width' => $width]),
        );
    }
}
