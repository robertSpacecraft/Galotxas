<?php

namespace App\Services;

use App\Models\User;
use App\Services\Media\ImagePreparationPolicy;
use App\Services\Media\ResponsiveImagePreparer;
use App\Services\Media\ResponsiveImageProfile;
use App\Services\Media\ResponsiveMediaLifecycle;
use App\Services\Media\ResponsiveMediaStorage;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class ProfilePhotoService
{
    public function __construct(
        private readonly ResponsiveImagePreparer $images,
        private readonly ResponsiveMediaStorage $storage,
        private readonly ResponsiveMediaLifecycle $lifecycle,
    ) {}

    public function store(User $user, UploadedFile $photo): User
    {
        $set = $this->storage->store($this->images->prepare(
            $photo, ResponsiveImageProfile::Avatar, ImagePreparationPolicy::Photo,
        ));

        return $this->lifecycle->mutate($set, function (callable $obsolete) use ($user, $set): User {
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $obsolete($locked->profile_photo_path, ResponsiveImageProfile::Avatar);
            if (! $locked->forceFill(['profile_photo_path' => $set->masterKey])->save()) {
                throw new RuntimeException('No se pudo guardar la foto de perfil.');
            }

            return $locked;
        });
    }

    public function remove(User $user): User
    {
        return $this->lifecycle->mutate(null, function (callable $obsolete) use ($user): User {
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $obsolete($locked->profile_photo_path, ResponsiveImageProfile::Avatar);
            if (! $locked->forceFill(['profile_photo_path' => null])->save()) {
                throw new RuntimeException('No se pudo retirar la foto de perfil.');
            }

            return $locked;
        });
    }

    public function deleteUser(User $user): void
    {
        $this->lifecycle->mutate(null, function (callable $obsolete) use ($user): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $obsolete($locked->profile_photo_path, ResponsiveImageProfile::Avatar);
            if (! $locked->delete()) {
                throw new RuntimeException('No se pudo eliminar el usuario.');
            }
        });
    }
}
