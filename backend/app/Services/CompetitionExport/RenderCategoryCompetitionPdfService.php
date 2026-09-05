<?php

namespace App\Services\CompetitionExport;

use App\Exceptions\CategoryCompetitionExportException;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

final class RenderCategoryCompetitionPdfService
{
    public const MAX_FILENAME_BYTES = 150;

    private const PRESETS = [
        'standard' => [
            'margin_mm' => 8,
            'table_font_pt' => 7.5,
            'line_height' => 1.10,
            'cell_padding_mm' => 0.65,
            'title_font_pt' => 11,
            'meta_font_pt' => 7.5,
            'participant_font_pt' => 7.25,
            'section_font_pt' => 9,
            'gap_mm' => 2.0,
        ],
        'compact' => [
            'margin_mm' => 6,
            'table_font_pt' => 6.75,
            'line_height' => 1.05,
            'cell_padding_mm' => 0.40,
            'title_font_pt' => 10,
            'meta_font_pt' => 7,
            'participant_font_pt' => 6.5,
            'section_font_pt' => 8,
            'gap_mm' => 1.2,
        ],
        'dense' => [
            'margin_mm' => 5,
            'table_font_pt' => 6,
            'line_height' => 1.00,
            'cell_padding_mm' => 0.20,
            'title_font_pt' => 9,
            'meta_font_pt' => 6.5,
            'participant_font_pt' => 6,
            'section_font_pt' => 7,
            'gap_mm' => 0.6,
        ],
    ];

    public function render(
        CategoryCompetitionExportDocument $document,
    ): RenderedCategoryCompetitionPdf {
        $temporaryDirectory = $this->createTemporaryDirectory();

        try {
            foreach (self::PRESETS as $presetName => $preset) {
                $dompdf = $this->newDompdf($temporaryDirectory);
                $html = view('admin.categories.export-pdf', [
                    'document' => $document,
                    'preset' => $preset,
                ])->render();

                $dompdf->setPaper('A4', 'portrait');
                $dompdf->loadHtml($html, 'UTF-8');
                $dompdf->render();

                $pageCount = $dompdf->getCanvas()->get_page_count();
                if ($pageCount === 1) {
                    return new RenderedCategoryCompetitionPdf(
                        bytes: $dompdf->output(),
                        filename: $this->filename($document),
                        preset: $presetName,
                        pageCount: $pageCount,
                    );
                }

                unset($html, $dompdf);
                gc_collect_cycles();
            }
        } finally {
            File::deleteDirectory($temporaryDirectory);
        }

        throw new CategoryCompetitionExportException(
            CategoryCompetitionExportException::OVERFLOW
        );
    }

    public function filename(CategoryCompetitionExportDocument $document): string
    {
        $segments = [];

        if ($document->seasonName !== null) {
            $segments[] = Str::slug($document->seasonName);
        }

        $segments[] = Str::slug($document->championshipName);
        $segments[] = Str::slug($document->categoryName);
        $segments = array_values(array_filter($segments, fn (string $segment): bool => $segment !== ''));
        $basename = implode('-', $segments);

        if ($basename === '') {
            $basename = 'temporada-campeonato-categoria';
        }

        $maximumBasenameBytes = self::MAX_FILENAME_BYTES - strlen('.pdf');
        $basename = rtrim(substr($basename, 0, $maximumBasenameBytes), '-');

        if ($basename === '') {
            $basename = 'temporada-campeonato-categoria';
        }

        return $basename.'.pdf';
    }

    private function newDompdf(string $temporaryDirectory): Dompdf
    {
        $options = new Options;
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setIsFontSubsettingEnabled(true);
        $options->setTempDir($temporaryDirectory);

        return new Dompdf($options);
    }

    private function createTemporaryDirectory(): string
    {
        $root = storage_path('framework/cache/data');

        if (! File::isDirectory($root) && ! File::makeDirectory($root, 0750, true)) {
            throw new RuntimeException('No se ha podido preparar el directorio temporal de PDF.');
        }

        $directory = $root.DIRECTORY_SEPARATOR.'dompdf-'.Str::uuid()->toString();

        if (! File::makeDirectory($directory, 0700)) {
            throw new RuntimeException('No se ha podido crear el directorio temporal del PDF.');
        }

        return $directory;
    }
}
