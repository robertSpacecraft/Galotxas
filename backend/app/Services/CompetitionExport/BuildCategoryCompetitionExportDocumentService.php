<?php

namespace App\Services\CompetitionExport;

use App\Enums\ChampionshipType;
use App\Exceptions\CategoryCompetitionExportException;
use App\Models\Category;
use App\Models\GameMatch;
use App\Models\Round;
use App\Services\PublicPlayerIdentityService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

final readonly class BuildCategoryCompetitionExportDocumentService
{
    private const CUP_STAGE_ORDER = [
        'semifinal' => 1,
        'final' => 2,
        'third_place' => 3,
    ];

    private const CUP_STAGE_LABELS = [
        'semifinal' => 'Semifinal',
        'final' => 'Final',
        'third_place' => '3.º/4.º puesto',
    ];

    public function __construct(
        private PublicPlayerIdentityService $publicIdentity,
        private CompetitionExportResultProjector $resultProjector,
    ) {}

    public function build(
        Category $category,
        ?CarbonImmutable $asOf = null,
    ): CategoryCompetitionExportDocument {
        $exportedAt = $asOf ?? CarbonImmutable::now();

        return DB::transaction(function () use ($category, $exportedAt): CategoryCompetitionExportDocument {
            // MariaDB REPEATABLE READ keeps the eager-load queries in one coherent live snapshot.
            $snapshot = Category::query()
                ->with([
                    'championship.season',
                    'entries' => fn ($query) => $query
                        ->where('status', 'approved')
                        ->orderBy('id'),
                    'entries.player.user',
                    'entries.player.publicIdentityAuthorizations',
                    'entries.team',
                    'rounds' => fn ($query) => $query
                        ->orderBy('order')
                        ->orderBy('id'),
                    'rounds.matches' => fn ($query) => $query
                        ->orderByRaw('scheduled_date IS NULL')
                        ->orderBy('scheduled_date')
                        ->orderBy('id'),
                    'rounds.matches.homeEntry',
                    'rounds.matches.awayEntry',
                    'rounds.matches.venue',
                ])
                ->findOrFail($category->getKey());

            $entryNames = [];
            foreach ($snapshot->entries as $entry) {
                $entryNames[(int) $entry->id] = $this->publicIdentity
                    ->entryDisplayName($entry, $exportedAt);
            }

            $leagueRounds = new EloquentCollection;
            $cupRounds = new EloquentCollection;

            foreach ($snapshot->rounds as $round) {
                $kind = $this->classifyRound($round);

                if ($kind === 'league') {
                    $leagueRounds->push($round);
                } else {
                    $cupRounds->push($round);
                }
            }

            $leagueMatches = $this->buildLeagueRows(
                $leagueRounds,
                $entryNames,
                $snapshot->championship->type,
            );
            $cupMatches = $this->buildCupRows(
                $cupRounds,
                $entryNames,
                $snapshot->championship->type,
            );

            if ($leagueMatches === [] && $cupMatches === []) {
                throw new CategoryCompetitionExportException(
                    CategoryCompetitionExportException::NO_MATCHES
                );
            }

            $participants = array_values($entryNames);
            $seasonName = trim((string) ($snapshot->championship->season?->name ?? ''));

            return new CategoryCompetitionExportDocument(
                exportedAt: $exportedAt,
                seasonName: $seasonName !== '' ? $seasonName : null,
                championshipName: trim((string) $snapshot->championship->name),
                categoryName: trim((string) $snapshot->name),
                modalityLabel: $snapshot->championship->type === ChampionshipType::DOUBLES
                    ? 'Dobles'
                    : 'Individual',
                participantCount: count($participants),
                participants: $participants,
                leagueMatches: $leagueMatches,
                cupMatches: $cupMatches,
            );
        }, 1);
    }

    private function classifyRound(Round $round): string
    {
        $legacyLeague = $round->type === 'league'
            && $round->phase === null
            && $round->stage === null;
        $normalizedLeague = $round->type === 'league'
            && $round->phase === 'league'
            && $round->stage === 'matchday';
        $recognizedCup = $round->type === 'cup'
            && $round->phase === 'cup'
            && array_key_exists((string) $round->stage, self::CUP_STAGE_ORDER);

        if ($legacyLeague || $normalizedLeague) {
            if ((int) $round->order < 1) {
                throw new CategoryCompetitionExportException(
                    CategoryCompetitionExportException::AMBIGUOUS_STRUCTURE
                );
            }

            return 'league';
        }

        if ($recognizedCup) {
            return 'cup';
        }

        throw new CategoryCompetitionExportException(
            CategoryCompetitionExportException::AMBIGUOUS_STRUCTURE
        );
    }

    /**
     * @param  EloquentCollection<int, Round>  $rounds
     * @param  array<int, string>  $entryNames
     * @return list<CategoryCompetitionExportMatchRow>
     */
    private function buildLeagueRows(
        EloquentCollection $rounds,
        array $entryNames,
        ChampionshipType|string $championshipType,
    ): array {
        $rows = [];
        $orderedRounds = $rounds->sortBy(
            fn (Round $round): string => sprintf('%020d|%020d', $round->order, $round->id)
        );

        foreach ($orderedRounds as $round) {
            foreach ($this->orderedMatches($round) as $match) {
                $rows[] = $this->matchRow(
                    $match,
                    'Jornada '.(int) $round->order,
                    $entryNames,
                    $championshipType,
                );
            }
        }

        return $rows;
    }

    /**
     * @param  EloquentCollection<int, Round>  $rounds
     * @param  array<int, string>  $entryNames
     * @return list<CategoryCompetitionExportMatchRow>
     */
    private function buildCupRows(
        EloquentCollection $rounds,
        array $entryNames,
        ChampionshipType|string $championshipType,
    ): array {
        $rows = [];
        $matches = [];

        foreach ($rounds as $round) {
            foreach ($round->matches as $match) {
                $matches[] = [
                    'stage' => (string) $round->stage,
                    'match' => $match,
                ];
            }
        }

        usort($matches, function (array $left, array $right): int {
            $leftMatch = $left['match'];
            $rightMatch = $right['match'];

            return [
                self::CUP_STAGE_ORDER[$left['stage']],
                $leftMatch->scheduled_date === null ? 1 : 0,
                $leftMatch->scheduled_date?->format('Y-m-d H:i:s.u') ?? '',
                $leftMatch->id,
            ] <=> [
                self::CUP_STAGE_ORDER[$right['stage']],
                $rightMatch->scheduled_date === null ? 1 : 0,
                $rightMatch->scheduled_date?->format('Y-m-d H:i:s.u') ?? '',
                $rightMatch->id,
            ];
        });

        foreach ($matches as $item) {
            $rows[] = $this->matchRow(
                $item['match'],
                self::CUP_STAGE_LABELS[$item['stage']],
                $entryNames,
                $championshipType,
            );
        }

        return $rows;
    }

    /**
     * @return EloquentCollection<int, GameMatch>
     */
    private function orderedMatches(Round $round): EloquentCollection
    {
        return $round->matches
            ->sortBy(fn (GameMatch $match): string => sprintf(
                '%d|%s|%020d',
                $match->scheduled_date === null ? 1 : 0,
                $match->scheduled_date?->format('Y-m-d H:i:s.u') ?? '',
                $match->id,
            ))
            ->values();
    }

    /**
     * @param  array<int, string>  $entryNames
     */
    private function matchRow(
        GameMatch $match,
        string $groupLabel,
        array $entryNames,
        ChampionshipType|string $championshipType,
    ): CategoryCompetitionExportMatchRow {
        $homeEntryId = (int) $match->home_entry_id;
        $awayEntryId = (int) $match->away_entry_id;

        if (
            $match->homeEntry === null
            || $match->awayEntry === null
            || ! array_key_exists($homeEntryId, $entryNames)
            || ! array_key_exists($awayEntryId, $entryNames)
        ) {
            throw new CategoryCompetitionExportException(
                CategoryCompetitionExportException::INVALID_PARTICIPANTS
            );
        }

        return new CategoryCompetitionExportMatchRow(
            groupLabel: $groupLabel,
            date: $match->scheduled_date?->format('d/m/Y'),
            time: $match->scheduled_date?->format('H:i'),
            venue: $match->venue === null
                ? null
                : trim((string) $match->venue->name),
            homeDisplayName: $entryNames[$homeEntryId],
            awayDisplayName: $entryNames[$awayEntryId],
            resultText: $this->resultProjector->project($match, $championshipType),
        );
    }
}
