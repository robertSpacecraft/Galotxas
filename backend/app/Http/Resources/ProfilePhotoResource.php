<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Services\Media\MediaObjectKeyGenerator;
use App\Services\Media\MediaPurpose;
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
     * @return array{url: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'url' => route('api.v1.me.profile-photo.image'),
        ];
    }
}
