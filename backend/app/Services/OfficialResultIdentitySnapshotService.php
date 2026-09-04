<?php

namespace App\Services;

use App\Exceptions\OfficialResultSourceIntegrityException;
use App\Models\CategoryEntry;
use Carbon\CarbonInterface;

class OfficialResultIdentitySnapshotService
{
    public function __construct(
        private readonly PublicPlayerIdentityService $publicIdentity,
        private readonly UnicodeTextService $text,
    ) {}

    public function snapshot(
        CategoryEntry $entry,
        CarbonInterface $asOf,
    ): OfficialResultIdentitySnapshot {
        if ($entry->entry_type === 'player' && $entry->relationLoaded('player') && $entry->player) {
            $displayName = $this->text->squish($entry->player->nickname);

            if ($displayName === '') {
                $displayName = $this->text->squish(
                    ($entry->player->user?->name ?? '').' '.($entry->player->user?->lastname ?? '')
                );
            }

            $displayName = $displayName !== '' ? $displayName : 'Participante #'.$entry->id;
        } elseif ($entry->entry_type === 'team' && $entry->relationLoaded('team') && $entry->team) {
            $displayName = $this->text->squish($entry->team->name);
            $displayName = $displayName !== '' ? $displayName : 'Equipo #'.$entry->id;
        } else {
            throw new OfficialResultSourceIntegrityException('No se puede resolver la identidad de una entrada oficial.');
        }

        $public = $this->publicIdentity->resolveEntry($entry, $asOf);

        if (
            $displayName === ''
            || $public->displayName === ''
            || mb_strlen($displayName) > 255
            || mb_strlen($public->displayName) > 255
        ) {
            throw new OfficialResultSourceIntegrityException('Una identidad oficial no cabe en el snapshot persistente.');
        }

        return new OfficialResultIdentitySnapshot(
            $public->projection,
            $displayName,
            $public->displayName,
        );
    }
}
