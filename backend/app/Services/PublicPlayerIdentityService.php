<?php

namespace App\Services;

use App\Models\CategoryEntry;
use App\Models\Player;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class PublicPlayerIdentityService
{
    public const NEUTRAL_LABEL = 'Participante';

    public function displayName(Player $player, ?CarbonInterface $asOf = null): string
    {
        if (! $this->isAdult($player, $asOf) || ! $player->relationLoaded('user')) {
            return self::NEUTRAL_LABEL;
        }

        $nickname = $this->normalize($player->nickname);

        if ($nickname !== '') {
            return $nickname;
        }

        $name = $this->normalize($player->user?->name);
        $lastname = $this->normalize($player->user?->lastname);

        if ($name === '' || $lastname === '') {
            return self::NEUTRAL_LABEL;
        }

        return $name.' '.mb_strtoupper(mb_substr($lastname, 0, 1)).'.';
    }

    public function entryDisplayName(CategoryEntry $entry, ?CarbonInterface $asOf = null): string
    {
        if ($entry->entry_type === 'player' && $entry->relationLoaded('player') && $entry->player) {
            return $this->displayName($entry->player, $asOf);
        }

        if ($entry->entry_type === 'team' && $entry->relationLoaded('team') && $entry->team) {
            $name = $this->normalize($entry->team->name);

            return $name !== '' ? $name : 'Equipo';
        }

        return self::NEUTRAL_LABEL;
    }

    private function isAdult(Player $player, ?CarbonInterface $asOf): bool
    {
        if ($player->birth_date === null) {
            return false;
        }

        $birthDate = CarbonImmutable::instance($player->birth_date)->startOfDay();
        $referenceDate = $asOf
            ? CarbonImmutable::instance($asOf)->startOfDay()
            : CarbonImmutable::today();

        return $birthDate->lessThanOrEqualTo($referenceDate->subYears(18));
    }

    private function normalize(?string $value): string
    {
        return preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '';
    }
}
