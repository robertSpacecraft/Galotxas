<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\CategoryCompetitionExportException;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\CompetitionExport\BuildCategoryCompetitionExportDocumentService;
use App\Services\CompetitionExport\RenderCategoryCompetitionPdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class CategoryCompetitionExportController extends Controller
{
    public function __invoke(
        Category $category,
        BuildCategoryCompetitionExportDocumentService $builder,
        RenderCategoryCompetitionPdfService $renderer,
    ): Response|RedirectResponse {
        try {
            $document = $builder->build($category);
            $pdf = $renderer->render($document);
        } catch (CategoryCompetitionExportException $exception) {
            return redirect()
                ->route('admin.categories.show', $category)
                ->with('error', $exception->getMessage());
        }

        return response($pdf->bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf->filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
