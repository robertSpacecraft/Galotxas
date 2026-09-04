<?php

namespace App\Exceptions;

use App\Services\LeagueOfficializationReadiness;
use DomainException;

class LeagueOfficializationNotReadyException extends DomainException
{
    public function __construct(
        public readonly LeagueOfficializationReadiness $readiness,
    ) {
        parent::__construct('La Liga no reúne las condiciones para oficializarse.');
    }

    /** @return list<string> */
    public function reasonCodes(): array
    {
        return $this->readiness->reasonCodes();
    }

    /** @return list<array{code: string, context: array<string, int|string|list<int>>}> */
    public function safeIssues(): array
    {
        return $this->readiness->safeIssues();
    }
}
