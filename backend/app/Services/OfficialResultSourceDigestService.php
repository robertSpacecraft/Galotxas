<?php

namespace App\Services;

use App\Services\Ranking\ResolveMatchBasePointsService;

class OfficialResultSourceDigestService
{
    public function __construct(
        private readonly ResolveMatchBasePointsService $basePoints,
    ) {}

    /** @return array<string, mixed> */
    public function leaguePayload(LeagueOfficializationSource $source): array
    {
        return [
            'schema' => 'league-source-v1',
            'competition_part' => 'league',
            'category_id' => $source->category->id,
            'championship_type' => $source->championshipType,
            'rules' => $this->basePoints->canonicalRuleset($source->targetScore),
            'entries' => collect($source->entries)
                ->sortBy('source_entry_id')
                ->map(function (array $entry): array {
                    $entry['team_members'] = collect($entry['team_members'])
                        ->sortBy(fn (array $member): string => sprintf(
                            '%020d|%s',
                            $member['source_player_id'],
                            $member['role'],
                        ))
                        ->values()
                        ->all();

                    return $entry;
                })
                ->values()
                ->all(),
            'matches' => collect($source->matches)
                ->sortBy('source_game_match_id')
                ->values()
                ->all(),
            'ranking' => collect($source->ranking)
                ->sortBy('position')
                ->values()
                ->all(),
        ];
    }

    public function leagueDigest(LeagueOfficializationSource $source): string
    {
        return $this->hashPayload($this->leaguePayload($source));
    }

    /** @param array<string, mixed> $payload */
    public function hashPayload(array $payload): string
    {
        return hash('sha256', $this->canonicalJson($payload));
    }

    /** @param array<string, mixed> $payload */
    public function canonicalJson(array $payload): string
    {
        return json_encode(
            $this->canonicalize($payload),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value, SORT_STRING);

        return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
    }
}
