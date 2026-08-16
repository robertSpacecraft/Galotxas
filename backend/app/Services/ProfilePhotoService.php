<?php

namespace App\Services;

use App\Models\User;
use App\Services\Media\Exceptions\MediaStorageException;
use App\Services\Media\ImageNormalizer;
use App\Services\Media\MediaObjectKeyGenerator;
use App\Services\Media\MediaPurpose;
use App\Services\Media\MediaStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProfilePhotoService
{
    public function __construct(
        private readonly ImageNormalizer $images,
        private readonly MediaStorageService $storage,
        private readonly MediaObjectKeyGenerator $keys,
    ) {}

    public function store(User $user, UploadedFile $photo): User
    {
        $image = $this->images->normalize($photo, 'avatar');
        $newKey = $this->storage->store(MediaPurpose::Avatar, $image);
        $oldKey = null;

        try {
            $updated = DB::transaction(function () use ($user, $newKey, &$oldKey): User {
                $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());
                $oldKey = $this->validAvatarKey($locked->profile_photo_path);
                $locked->forceFill(['profile_photo_path' => $newKey])->save();

                return $locked;
            });
        } catch (Throwable $exception) {
            $this->cleanup($newKey, 'store_compensation');

            throw $exception;
        }

        if ($oldKey !== null && $oldKey !== $newKey) {
            $this->cleanup($oldKey, 'replace_old_object');
        }

        return $updated;
    }

    public function remove(User $user): User
    {
        $oldKey = null;

        $updated = DB::transaction(function () use ($user, &$oldKey): User {
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $oldKey = $this->validAvatarKey($locked->profile_photo_path);
            $locked->forceFill(['profile_photo_path' => null])->save();

            return $locked;
        });

        if ($oldKey !== null) {
            $this->cleanup($oldKey, 'remove_object');
        }

        return $updated;
    }

    public function deleteUser(User $user): void
    {
        $oldKey = null;

        DB::transaction(function () use ($user, &$oldKey): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $oldKey = $this->validAvatarKey($locked->profile_photo_path);
            $locked->delete();
        });

        if ($oldKey !== null) {
            $this->cleanup($oldKey, 'delete_user_object');
        }
    }

    private function validAvatarKey(mixed $key): ?string
    {
        if (! is_string($key) || ! $this->keys->isValidForPurpose($key, MediaPurpose::Avatar)) {
            return null;
        }

        return $key;
    }

    private function cleanup(string $key, string $operation): void
    {
        try {
            $this->storage->delete($key);
        } catch (MediaStorageException) {
            Log::warning('Profile photo cleanup failed.', [
                'operation' => $operation,
            ]);
        }
    }
}
