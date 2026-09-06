<?php

namespace Tests\Unit;

use App\Enums\OfficialResultCompetitionPart;
use App\Services\Admin\OfficialResultReadinessIssueFormatter;
use PHPUnit\Framework\TestCase;

class OfficialResultReadinessIssueFormatterTest extends TestCase
{
    public function test_every_current_league_issue_has_a_human_message(): void
    {
        $this->assertKnownCodes(OfficialResultCompetitionPart::LEAGUE, [
            'league_already_official',
            'insufficient_entries',
            'incoherent_entry_type',
            'missing_entry_source',
            'invalid_team_composition',
            'ambiguous_round',
            'missing_league_round',
            'invalid_round_count',
            'empty_league_round',
            'entry_repeated_in_round',
            'self_pairing',
            'foreign_entry',
            'duplicate_pairing',
            'match_not_validated',
            'missing_score',
            'tied_match',
            'invalid_score',
            'missing_winner',
            'inconsistent_winner',
            'extra_league_match',
            'incomplete_round_robin',
            'unsupported_ranking_tie',
        ]);
    }

    public function test_every_current_cup_issue_has_a_human_message(): void
    {
        $this->assertKnownCodes(OfficialResultCompetitionPart::CUP, [
            'cup_already_official',
            'insufficient_entries',
            'incoherent_entry_type',
            'missing_entry_source',
            'invalid_team_composition',
            'ambiguous_cup_round',
            'missing_semifinal_round',
            'duplicate_semifinal_round',
            'missing_final_round',
            'duplicate_final_round',
            'duplicate_third_place_round',
            'invalid_semifinal_match_count',
            'invalid_final_match_count',
            'self_pairing',
            'foreign_entry',
            'match_not_validated',
            'missing_score',
            'tied_match',
            'invalid_score',
            'missing_winner',
            'inconsistent_winner',
            'duplicate_semifinal_participant',
            'source_integrity_error',
            'unsupported_cup_seed_tie',
            'invalid_cup_seed',
            'inconsistent_finalists',
        ]);
    }

    public function test_messages_use_safe_context_and_unknown_codes_fail_closed(): void
    {
        $formatter = new OfficialResultReadinessIssueFormatter;
        $known = $formatter->format(OfficialResultCompetitionPart::LEAGUE, [[
            'code' => 'match_not_validated',
            'context' => ['match_id' => 73],
        ]]);
        $unknown = $formatter->format(OfficialResultCompetitionPart::CUP, [[
            'code' => 'future_rule<script>',
            'context' => [],
        ]]);

        $this->assertSame('El partido todavía no está validado (partido #73).', $known[0]);
        $this->assertStringContainsString('future_rule?script?', $unknown[0]);
        $this->assertStringContainsString('no se puede oficializar', $unknown[0]);
        $this->assertStringNotContainsString('<script>', $unknown[0]);
    }

    /** @param list<string> $codes */
    private function assertKnownCodes(
        OfficialResultCompetitionPart $part,
        array $codes,
    ): void {
        $formatter = new OfficialResultReadinessIssueFormatter;
        $context = [
            'approved_entries' => 2,
            'entry_id' => 11,
            'round_id' => 22,
            'round_ids' => [22, 23],
            'match_id' => 33,
            'entry_ids' => [11, 12],
            'expected' => 3,
            'actual' => 2,
            'missing_pairs' => 1,
        ];

        foreach ($codes as $code) {
            $messages = $formatter->format($part, [[
                'code' => $code,
                'context' => $context,
            ]]);

            $this->assertCount(1, $messages, $code);
            $this->assertNotSame('', trim($messages[0]), $code);
            $this->assertStringNotContainsString('No se reconoce', $messages[0], $code);
        }
    }
}
