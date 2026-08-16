<?php

namespace App\Services;

use App\Models\Sponsor;
use App\Services\Media\Exceptions\MediaStorageException;
use App\Services\Media\ImageNormalizer;
use App\Services\Media\MediaPurpose;
use App\Services\Media\MediaStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SponsorService
{
    public function __construct(
        private readonly ImageNormalizer $images,
        private readonly MediaStorageService $storage,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, UploadedFile $logo): Sponsor
    {
        $image = $this->images->normalize($logo, 'sponsor_logo');
        $newKey = $this->storage->store(MediaPurpose::Sponsor, $image);

        try {
            return DB::transaction(function () use ($attributes, $image, $newKey): Sponsor {
                return Sponsor::query()->create([
                    ...$attributes,
                    'logo_key' => $newKey,
                    'logo_width' => $image->width,
                    'logo_height' => $image->height,
                ]);
            });
        } catch (Throwable $exception) {
            $this->cleanup($newKey, 'create_compensation');

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(
        Sponsor $sponsor,
        array $attributes,
        ?UploadedFile $logo = null
    ): Sponsor {
        if ($logo === null) {
            return DB::transaction(function () use ($sponsor, $attributes): Sponsor {
                $locked = Sponsor::query()->lockForUpdate()->findOrFail($sponsor->getKey());
                $locked->fill($attributes)->save();

                return $locked;
            });
        }

        $image = $this->images->normalize($logo, 'sponsor_logo');
        $newKey = $this->storage->store(MediaPurpose::Sponsor, $image);
        $oldKey = null;

        try {
            $updated = DB::transaction(function () use (
                $sponsor,
                $attributes,
                $image,
                $newKey,
                &$oldKey
            ): Sponsor {
                $locked = Sponsor::query()->lockForUpdate()->findOrFail($sponsor->getKey());
                $oldKey = $locked->logo_key;
                $locked->fill([
                    ...$attributes,
                    'logo_key' => $newKey,
                    'logo_width' => $image->width,
                    'logo_height' => $image->height,
                ])->save();

                return $locked;
            });
        } catch (Throwable $exception) {
            $this->cleanup($newKey, 'replace_compensation');

            throw $exception;
        }

        if (is_string($oldKey)) {
            $this->cleanup($oldKey, 'replace_old_object');
        }

        return $updated;
    }

    public function delete(Sponsor $sponsor): void
    {
        $oldKey = null;

        DB::transaction(function () use ($sponsor, &$oldKey): void {
            $locked = Sponsor::query()->lockForUpdate()->findOrFail($sponsor->getKey());
            $oldKey = $locked->logo_key;
            $locked->delete();
        });

        if (is_string($oldKey)) {
            $this->cleanup($oldKey, 'delete_object');
        }
    }

    private function cleanup(string $key, string $operation): void
    {
        try {
            $this->storage->delete($key);
        } catch (MediaStorageException) {
            Log::warning('Sponsor media cleanup failed.', [
                'operation' => $operation,
            ]);
        }
    }
}
