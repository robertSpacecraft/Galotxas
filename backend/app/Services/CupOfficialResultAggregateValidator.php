<?php

namespace App\Services;

use App\Enums\OfficialResultCompetitionPart;
use App\Exceptions\OfficialResultSourceIntegrityException;
use App\Models\CategoryOfficialResult;

class CupOfficialResultAggregateValidator
{
    public function validate(CategoryOfficialResult $result): void
    {
        $result->load([
            'leagueRows',
            'cupWinner',
            'matchSnapshots' => fn ($query) => $query->orderBy('id'),
        ]);

        $winner = $result->cupWinner;
        $snapshots = $result->matchSnapshots;
        $semifinals = $snapshots->where('stage', 'semifinal')->values();
        $finals = $snapshots->where('stage', 'final')->values();

        if (
            $result->competition_part !== OfficialResultCompetitionPart::CUP
            || $result->leagueRows->isNotEmpty()
            || $winner === null
            || $result->cupWinner()->count() !== 1
            || $snapshots->count() !== 3
            || $semifinals->count() !== 2
            || $finals->count() !== 1
            || $snapshots->pluck('source_game_match_id')->unique()->count() !== 3
        ) {
            throw new OfficialResultSourceIntegrityException(
                'El agregado Cup no contiene exactamente un campeón y tres partidos decisivos.'
            );
        }

        if (
            (int) $winner->source_entry_id <= 0
            || (int) $winner->source_final_match_id <= 0
            || trim((string) $winner->display_name_snapshot) === ''
            || ! $this->hasCoherentEntrySource($winner)
        ) {
            throw new OfficialResultSourceIntegrityException(
                'El snapshot de campeón Cup no contiene una fuente coherente.'
            );
        }

        foreach ($snapshots as $snapshot) {
            if (
                (int) $snapshot->source_game_match_id <= 0
                || (int) $snapshot->source_round_id <= 0
                || ! in_array($snapshot->stage, ['semifinal', 'final'], true)
                || $snapshot->home_entry_id === $snapshot->away_entry_id
                || $snapshot->home_score === $snapshot->away_score
                || ! in_array(
                    (int) $snapshot->winner_entry_id,
                    [(int) $snapshot->home_entry_id, (int) $snapshot->away_entry_id],
                    true,
                )
                || (int) $snapshot->winner_entry_id !== (
                    $snapshot->home_score > $snapshot->away_score
                        ? (int) $snapshot->home_entry_id
                        : (int) $snapshot->away_entry_id
                )
            ) {
                throw new OfficialResultSourceIntegrityException(
                    'La evidencia de partidos Cup contiene un resultado incoherente.'
                );
            }
        }

        $final = $finals->first();
        $semifinalWinners = $semifinals
            ->pluck('winner_entry_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
        $finalists = collect([
            (int) $final->home_entry_id,
            (int) $final->away_entry_id,
        ])->sort()->values()->all();

        if (
            count($semifinalWinners) !== 2
            || $finalists !== $semifinalWinners
            || (int) $winner->source_final_match_id !== (int) $final->source_game_match_id
            || (int) $winner->source_entry_id !== (int) $final->winner_entry_id
        ) {
            throw new OfficialResultSourceIntegrityException(
                'El campeón Cup no coincide con la Final derivada de semifinales.'
            );
        }
    }

    private function hasCoherentEntrySource(object $winner): bool
    {
        return ($winner->entry_type === 'player'
                && $winner->source_player_id !== null
                && $winner->source_team_id === null)
            || ($winner->entry_type === 'team'
                && $winner->source_player_id === null
                && $winner->source_team_id !== null);
    }
}
