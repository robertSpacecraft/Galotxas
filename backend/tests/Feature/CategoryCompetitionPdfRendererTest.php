<?php

namespace Tests\Feature;

use App\Exceptions\CategoryCompetitionExportException;
use App\Services\CompetitionExport\CategoryCompetitionExportDocument;
use App\Services\CompetitionExport\CategoryCompetitionExportMatchRow;
use App\Services\CompetitionExport\RenderCategoryCompetitionPdfService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

class CategoryCompetitionPdfRendererTest extends TestCase
{
    public function test_renderer_presets_and_filename_respect_the_approved_limits(): void
    {
        $reflection = new ReflectionClass(RenderCategoryCompetitionPdfService::class);
        $presets = $reflection->getReflectionConstant('PRESETS')->getValue();

        $this->assertSame([
            'standard' => [8, 7.5, 1.10, 0.65],
            'compact' => [6, 6.75, 1.05, 0.40],
            'dense' => [5, 6, 1.00, 0.20],
        ], array_map(
            fn (array $preset): array => [
                $preset['margin_mm'],
                $preset['table_font_pt'],
                $preset['line_height'],
                $preset['cell_padding_mm'],
            ],
            $presets
        ));

        $document = $this->document(
            participants: ['Participante'],
            leagueRows: [$this->row()],
            season: "Temporada / 2026\r\nPrivada",
            championship: str_repeat('Campeonato muy largo ', 12),
            category: '../Categoría "especial"',
        );
        $filename = app(RenderCategoryCompetitionPdfService::class)->filename($document);

        $this->assertLessThanOrEqual(RenderCategoryCompetitionPdfService::MAX_FILENAME_BYTES, strlen($filename));
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+\.pdf$/', $filename);
        $this->assertStringNotContainsString("\r", $filename);
        $this->assertStringNotContainsString("\n", $filename);
        $this->assertStringNotContainsString('/', $filename);
        $this->assertStringNotContainsString('\\', $filename);

        $withoutSeason = $this->document(
            participants: ['Participante'],
            leagueRows: [$this->row()],
            season: null,
        );
        $this->assertSame(
            'campionat-de-la-ribera-primera-categoria.pdf',
            app(RenderCategoryCompetitionPdfService::class)->filename($withoutSeason)
        );

        $fallback = $this->document(
            participants: ['Participante'],
            leagueRows: [$this->row()],
            season: null,
            championship: ' / ',
            category: "\r\n",
        );
        $this->assertSame(
            'temporada-campeonato-categoria.pdf',
            app(RenderCategoryCompetitionPdfService::class)->filename($fallback)
        );
    }

    public function test_pdf_view_escapes_values_and_has_no_destructive_truncation_or_remote_dependency(): void
    {
        $document = $this->document(
            participants: ['<script>dato privado</script>'],
            leagueRows: [$this->row(home: str_repeat('Nombre largo con espacios ', 8))]
        );
        $html = view('admin.categories.export-pdf', [
            'document' => $document,
            'preset' => $this->preset(),
        ])->render();

        $this->assertStringContainsString('&lt;script&gt;dato privado&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>dato privado</script>', $html);
        $this->assertStringNotContainsString('overflow: hidden', $html);
        $this->assertStringNotContainsString('text-overflow', $html);
        $this->assertStringNotContainsString('white-space: nowrap', $html);
        $this->assertStringNotContainsString('http://', $html);
        $this->assertStringNotContainsString('https://', $html);
        $this->assertStringContainsString('word-wrap: break-word', $html);
        $this->assertStringContainsString('size: A4 portrait', $html);
    }

    public function test_pdf_view_marks_group_starts_once_and_keeps_empty_results_writable(): void
    {
        $document = $this->document(
            leagueRows: [
                $this->row(groupLabel: 'Jornada 1', resultText: null, date: null, time: null, venue: null),
                $this->row(groupLabel: 'Jornada 1', resultText: 'Aplazado'),
                $this->row(groupLabel: 'Jornada 2', resultText: 'Cancelado'),
            ],
            cupRows: [
                $this->row(groupLabel: 'Semifinal', resultText: null),
                $this->row(groupLabel: 'Semifinal', resultText: '10-7'),
                $this->row(groupLabel: 'Final', resultText: null),
            ],
        );
        $html = view('admin.categories.export-pdf', [
            'document' => $document,
            'preset' => $this->preset(),
        ])->render();

        $this->assertSame(1, substr_count($html, '>Jornada 1</td>'));
        $this->assertSame(1, substr_count($html, '>Jornada 2</td>'));
        $this->assertSame(1, substr_count($html, '>Semifinal</td>'));
        $this->assertSame(1, substr_count($html, '>Final</td>'));
        $this->assertSame(4, substr_count($html, '<tr class="group-start">'));
        $this->assertSame(2, substr_count($html, '<tr class="group-continuation">'));
        $this->assertSame(3, substr_count($html, '<td class="result result-empty"></td>'));
        $this->assertStringContainsString('Aplazado', $html);
        $this->assertStringContainsString('Cancelado', $html);
        $this->assertStringContainsString('10-7', $html);
        $this->assertStringNotContainsString('—', $html);
        $this->assertStringNotContainsString('Pendiente', $html);
        $this->assertStringNotContainsString('Sin resultado', $html);
        $this->assertStringContainsString('matches tbody tr.group-start td', $html);
        $this->assertStringContainsString('matches td.result-empty', $html);
    }

    public function test_pdf_view_includes_only_the_available_competition_sections(): void
    {
        $preset = $this->preset();
        $leagueOnly = view('admin.categories.export-pdf', [
            'document' => $this->document(leagueRows: [$this->row()]),
            'preset' => $preset,
        ])->render();
        $cupOnly = view('admin.categories.export-pdf', [
            'document' => $this->document(
                cupRows: [$this->row()],
            ),
            'preset' => $preset,
        ])->render();

        $this->assertStringContainsString('<h2 class="section-title">Liga</h2>', $leagueOnly);
        $this->assertStringNotContainsString('<h2 class="section-title">Copa</h2>', $leagueOnly);
        $this->assertStringContainsString('<h2 class="section-title">Copa</h2>', $cupOnly);
        $this->assertStringNotContainsString('<h2 class="section-title">Liga</h2>', $cupOnly);
    }

    public function test_renderer_escalates_presets_and_still_returns_exactly_one_page(): void
    {
        $rows = array_fill(0, 70, $this->row());
        $document = $this->document(
            participants: array_map(fn (int $index): string => 'Participante '.$index, range(1, 10)),
            leagueRows: $rows,
        );

        $rendered = app(RenderCategoryCompetitionPdfService::class)->render($document);

        $this->assertSame(1, $rendered->pageCount);
        $this->assertContains($rendered->preset, ['compact', 'dense']);
        $this->assertStringStartsWith('%PDF-', $rendered->bytes);
    }

    public function test_long_names_wrap_without_truncation_and_render_on_one_page(): void
    {
        $longName = implode(' ', array_fill(0, 14, 'NomCognomRealista'));
        $document = $this->document(
            participants: [$longName, 'Rival'],
            leagueRows: [$this->row(home: $longName)],
        );

        $rendered = app(RenderCategoryCompetitionPdfService::class)->render($document);

        $this->assertSame(1, $rendered->pageCount);
        $this->assertStringStartsWith('%PDF-', $rendered->bytes);
    }

    public function test_oversize_content_fails_with_exact_error_and_never_returns_pdf(): void
    {
        $document = $this->document(
            participants: array_map(fn (int $index): string => 'Participante '.$index, range(1, 10)),
            leagueRows: array_fill(0, 220, $this->row()),
        );

        $this->expectException(CategoryCompetitionExportException::class);
        $this->expectExceptionMessage(CategoryCompetitionExportException::OVERFLOW);

        app(RenderCategoryCompetitionPdfService::class)->render($document);
    }

    public function test_renderer_cleans_only_its_request_directory_and_leaves_no_pdf_file(): void
    {
        $root = storage_path('framework/cache/data');
        File::ensureDirectoryExists($root, 0750, true);
        $sentinel = $root.DIRECTORY_SEPARATOR.'dompdf-sentinel-'.Str::uuid();
        File::makeDirectory($sentinel, 0700);
        File::put($sentinel.DIRECTORY_SEPARATOR.'concurrent-request.tmp', 'keep');
        $before = collect(File::directories($root))->sort()->values()->all();

        try {
            $rendered = app(RenderCategoryCompetitionPdfService::class)->render(
                $this->document(['Uno', 'Dos'], [$this->row()])
            );

            $this->assertSame(1, $rendered->pageCount);
            $this->assertFileExists($sentinel.DIRECTORY_SEPARATOR.'concurrent-request.tmp');
            $this->assertSame($before, collect(File::directories($root))->sort()->values()->all());
            $this->assertSame([], File::glob($root.DIRECTORY_SEPARATOR.'dompdf-*.pdf'));
        } finally {
            File::deleteDirectory($sentinel);
        }
    }

    /**
     * @param  list<string>  $participants
     * @param  list<CategoryCompetitionExportMatchRow>  $leagueRows
     * @param  list<CategoryCompetitionExportMatchRow>  $cupRows
     */
    private function document(
        array $participants = ['Uno', 'Dos'],
        array $leagueRows = [],
        array $cupRows = [],
        ?string $season = 'Temporada 2026',
        string $championship = 'Campionat de la Ribera',
        string $category = 'Primera Categoria',
    ): CategoryCompetitionExportDocument {
        return new CategoryCompetitionExportDocument(
            exportedAt: CarbonImmutable::parse('2026-09-05 12:00:00'),
            seasonName: $season,
            championshipName: $championship,
            categoryName: $category,
            modalityLabel: 'Individual',
            participantCount: count($participants),
            participants: $participants,
            leagueMatches: $leagueRows,
            cupMatches: $cupRows,
        );
    }

    private function row(
        string $home = 'Alba la Ràpida',
        string $groupLabel = 'Jornada 1',
        ?string $resultText = '10-7',
        ?string $date = '05/09/2026',
        ?string $time = '18:30',
        ?string $venue = 'Trinquet Municipal',
    ): CategoryCompetitionExportMatchRow {
        return new CategoryCompetitionExportMatchRow(
            groupLabel: $groupLabel,
            date: $date,
            time: $time,
            venue: $venue,
            homeDisplayName: $home,
            awayDisplayName: 'Bernat del Túria',
            resultText: $resultText,
        );
    }

    /** @return array<string, int|float> */
    private function preset(): array
    {
        return [
            'margin_mm' => 8,
            'table_font_pt' => 7.5,
            'line_height' => 1.10,
            'cell_padding_mm' => 0.65,
            'title_font_pt' => 11,
            'meta_font_pt' => 7.5,
            'participant_font_pt' => 7.25,
            'section_font_pt' => 9,
            'gap_mm' => 2.0,
        ];
    }
}
