<?php

namespace App\Services;

use App\Enums\OfficialIdentityProjection;
use App\Enums\PublicIdentityAuthorizationMode;
use App\Models\CategoryEntry;
use App\Models\Player;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class PublicPlayerIdentityService
{
    public const NEUTRAL_LABEL = 'Participante';

    public function __construct(
        private readonly PublicIdentityAuthorizationService $authorizationService,
        private readonly UnicodeTextService $text,
    ) {}

    public function displayName(Player $player, ?CarbonInterface $asOf = null): string
    {
        return $this->resolve($player, $asOf)->displayName;
    }

    public function resolve(Player $player, ?CarbonInterface $asOf = null): ResolvedPublicIdentity
    {
        if ($player->birth_date === null || ! $player->relationLoaded('user')) {
            return $this->anonymous();
        }

        if (! $this->isAdult($player, $asOf)) {
            if (! $player->relationLoaded('publicIdentityAuthorizations')) {
                return $this->anonymous();
            }

            $authorization = $player->publicIdentityAuthorizations->first(
                fn ($candidate): bool => $this->authorizationService->isEffectiveFor(
                    $candidate,
                    $player,
                    $asOf
                )
            );

            if ($authorization === null) {
                return $this->anonymous();
            }

            return match ($authorization->mode) {
                PublicIdentityAuthorizationMode::ALIAS => $this->aliasProjection($player),
                PublicIdentityAuthorizationMode::NAME_INITIAL => $this->nameInitialProjection($player),
                PublicIdentityAuthorizationMode::ANONYMOUS => $this->anonymous(),
            };
        }

        $alias = $this->normalizedAlias($player);

        return $alias !== ''
            ? new ResolvedPublicIdentity(OfficialIdentityProjection::ALIAS, $alias)
            : $this->nameInitialProjection($player);
    }

    public function entryDisplayName(CategoryEntry $entry, ?CarbonInterface $asOf = null): string
    {
        return $this->resolveEntry($entry, $asOf)->displayName;
    }

    public function resolveEntry(
        CategoryEntry $entry,
        ?CarbonInterface $asOf = null,
    ): ResolvedPublicIdentity {
        if ($entry->entry_type === 'player' && $entry->relationLoaded('player') && $entry->player) {
            return $this->resolve($entry->player, $asOf);
        }

        if ($entry->entry_type === 'team' && $entry->relationLoaded('team') && $entry->team) {
            $name = $this->text->squish($entry->team->name);

            return new ResolvedPublicIdentity(
                OfficialIdentityProjection::TEAM_NAME,
                $name !== '' ? $name : 'Equipo',
            );
        }

        return $this->anonymous();
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

    private function aliasProjection(Player $player): ResolvedPublicIdentity
    {
        $alias = $this->normalizedAlias($player);

        return $alias !== ''
            ? new ResolvedPublicIdentity(OfficialIdentityProjection::ALIAS, $alias)
            : $this->anonymous();
    }

    private function nameInitialProjection(Player $player): ResolvedPublicIdentity
    {
        $name = $this->text->squish($player->user?->name);
        $lastname = $this->text->squish($player->user?->lastname);

        return $name !== '' && $lastname !== ''
            ? new ResolvedPublicIdentity(
                OfficialIdentityProjection::NAME_INITIAL,
                $name.' '.mb_strtoupper(mb_substr($lastname, 0, 1)).'.',
            )
            : $this->anonymous();
    }

    private function normalizedAlias(Player $player): string
    {
        return $this->text->squish($player->nickname);
    }

    private function anonymous(): ResolvedPublicIdentity
    {
        return new ResolvedPublicIdentity(
            OfficialIdentityProjection::ANONYMOUS,
            self::NEUTRAL_LABEL,
        );
    }
}
