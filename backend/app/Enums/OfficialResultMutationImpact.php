<?php

namespace App\Enums;

enum OfficialResultMutationImpact
{
    case LEAGUE_RESULT;
    case LEAGUE_STRUCTURE;
    case CUP_DECISIVE;
    case PARTICIPANTS;
    case COMPETITION_RULES;
    case CATEGORY_DELETE;
    case AMBIGUOUS_MATCH;

    /**
     * @return list<OfficialResultCompetitionPart>
     */
    public function blockingParts(): array
    {
        return match ($this) {
            self::CUP_DECISIVE => [OfficialResultCompetitionPart::CUP],
            self::LEAGUE_RESULT,
            self::LEAGUE_STRUCTURE,
            self::PARTICIPANTS,
            self::COMPETITION_RULES,
            self::CATEGORY_DELETE,
            self::AMBIGUOUS_MATCH => [
                OfficialResultCompetitionPart::LEAGUE,
                OfficialResultCompetitionPart::CUP,
            ],
        };
    }
}
