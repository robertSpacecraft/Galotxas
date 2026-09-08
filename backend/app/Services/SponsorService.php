<?php

namespace App\Services;

use App\Models\Sponsor;
use App\Services\Media\ImagePreparationPolicy;
use App\Services\Media\ResponsiveImagePreparer;
use App\Services\Media\ResponsiveImageProfile;
use App\Services\Media\ResponsiveMediaLifecycle;
use App\Services\Media\ResponsiveMediaStorage;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class SponsorService
{
    public function __construct(
        private readonly ResponsiveImagePreparer $images,
        private readonly ResponsiveMediaStorage $storage,
        private readonly ResponsiveMediaLifecycle $lifecycle,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes, UploadedFile $logo): Sponsor
    {
        $set = $this->storage->store($this->images->prepare(
            $logo, ResponsiveImageProfile::SponsorLogo, ImagePreparationPolicy::Graphic,
        ));

        return $this->lifecycle->mutate($set, function () use ($attributes, $set): Sponsor {
            $sponsor = new Sponsor([
                ...$attributes,
                'logo_key' => $set->masterKey,
                'logo_width' => $set->manifest->master->width,
                'logo_height' => $set->manifest->master->height,
            ]);
            if (! $sponsor->save()) {
                throw new RuntimeException('No se pudo guardar el colaborador.');
            }

            return $sponsor;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(Sponsor $sponsor, array $attributes, ?UploadedFile $logo = null): Sponsor
    {
        $set = $logo === null ? null : $this->storage->store($this->images->prepare(
            $logo, ResponsiveImageProfile::SponsorLogo, ImagePreparationPolicy::Graphic,
        ));

        return $this->lifecycle->mutate($set, function (callable $obsolete) use ($sponsor, $attributes, $set): Sponsor {
            $locked = Sponsor::query()->lockForUpdate()->findOrFail($sponsor->getKey());
            if ($set !== null) {
                $obsolete($locked->logo_key, ResponsiveImageProfile::SponsorLogo);
                $attributes = [
                    ...$attributes,
                    'logo_key' => $set->masterKey,
                    'logo_width' => $set->manifest->master->width,
                    'logo_height' => $set->manifest->master->height,
                ];
            }
            if (! $locked->fill($attributes)->save()) {
                throw new RuntimeException('No se pudo guardar el colaborador.');
            }

            return $locked;
        });
    }

    public function delete(Sponsor $sponsor): void
    {
        $this->lifecycle->mutate(null, function (callable $obsolete) use ($sponsor): void {
            $locked = Sponsor::query()->lockForUpdate()->findOrFail($sponsor->getKey());
            $obsolete($locked->logo_key, ResponsiveImageProfile::SponsorLogo);
            if (! $locked->delete()) {
                throw new RuntimeException('No se pudo eliminar el colaborador.');
            }
        });
    }
}
