<?php

namespace App\Services\CompetitionExport;

use InvalidArgumentException;

final readonly class RenderedCategoryCompetitionPdf
{
    public function __construct(
        public string $bytes,
        public string $filename,
        public string $preset,
        public int $pageCount,
    ) {
        if ($pageCount !== 1) {
            throw new InvalidArgumentException('Un PDF exportable debe contener exactamente una página.');
        }
    }
}
