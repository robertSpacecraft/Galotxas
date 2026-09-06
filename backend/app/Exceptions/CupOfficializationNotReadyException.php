<?php

namespace App\Exceptions;

use App\Services\CupOfficializationReadiness;
use DomainException;

class CupOfficializationNotReadyException extends DomainException
{
    public function __construct(
        public readonly CupOfficializationReadiness $readiness,
    ) {
        parent::__construct('La Copa no reúne las condiciones para oficializarse.');
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
