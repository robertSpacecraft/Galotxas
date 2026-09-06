<?php

namespace App\Http\Resources\Concerns;

trait ResolvesOfficialSnapshotPublicName
{
    private function officialSnapshotPublicName(
        ?string $publicDisplayName,
        mixed $publicAnonymizedAt,
    ): string {
        $name = (string) $publicDisplayName;

        if ($publicAnonymizedAt !== null || trim($name) === '') {
            return 'Participante';
        }

        return $name;
    }
}
