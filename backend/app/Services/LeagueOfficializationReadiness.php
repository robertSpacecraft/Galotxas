<?php

namespace App\Services;

final readonly class LeagueOfficializationReadiness
{
    /** @param list<LeagueOfficializationIssue> $issues */
    public function __construct(
        public array $issues,
        public ?LeagueOfficializationSource $source = null,
    ) {}

    public function isReady(): bool
    {
        return $this->issues === [] && $this->source !== null;
    }

    /** @return list<string> */
    public function reasonCodes(): array
    {
        return array_values(array_unique(array_map(
            fn (LeagueOfficializationIssue $issue): string => $issue->code,
            $this->issues,
        )));
    }

    /** @return list<array{code: string, context: array<string, int|string|list<int>>}> */
    public function safeIssues(): array
    {
        return array_map(
            fn (LeagueOfficializationIssue $issue): array => $issue->toArray(),
            $this->issues,
        );
    }
}
