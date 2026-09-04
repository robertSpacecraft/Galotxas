<?php

namespace App\Services;

final readonly class LeagueOfficializationIssue
{
    /** @param array<string, int|string|list<int>> $context */
    public function __construct(
        public string $code,
        public array $context = [],
    ) {}

    /** @return array{code: string, context: array<string, int|string|list<int>>} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'context' => $this->context];
    }
}
