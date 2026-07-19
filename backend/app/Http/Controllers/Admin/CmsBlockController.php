<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CmsBlockType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCmsBlockRequest;
use App\Models\CmsBlock;
use App\Models\CmsPage;

class CmsBlockController extends Controller
{
    public function create(CmsPage $cmsPage)
    {
        return view('admin.cms-blocks.create', [
            'page' => $cmsPage,
            'block' => new CmsBlock([
                'type' => CmsBlockType::TEXT,
                'sort_order' => $this->nextSortOrder($cmsPage),
                'data' => [],
            ]),
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function store(SaveCmsBlockRequest $request, CmsPage $cmsPage)
    {
        $validated = $request->validated();

        $cmsPage->blocks()->create([
            'type' => $validated['type'],
            'sort_order' => $validated['sort_order'],
            'data' => $request->blockData(),
        ]);

        return redirect()
            ->route('admin.cms-pages.show', $cmsPage)
            ->with('success', 'Bloque CMS creado correctamente.');
    }

    public function edit(CmsPage $cmsPage, CmsBlock $cmsBlock)
    {
        $this->ensureBlockBelongsToPage($cmsPage, $cmsBlock);

        return view('admin.cms-blocks.edit', [
            'page' => $cmsPage,
            'block' => $cmsBlock,
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function update(SaveCmsBlockRequest $request, CmsPage $cmsPage, CmsBlock $cmsBlock)
    {
        $this->ensureBlockBelongsToPage($cmsPage, $cmsBlock);

        $validated = $request->validated();

        $cmsBlock->update([
            'type' => $validated['type'],
            'sort_order' => $validated['sort_order'],
            'data' => $request->blockData(),
        ]);

        return redirect()
            ->route('admin.cms-pages.show', $cmsPage)
            ->with('success', 'Bloque CMS actualizado correctamente.');
    }

    public function destroy(CmsPage $cmsPage, CmsBlock $cmsBlock)
    {
        $this->ensureBlockBelongsToPage($cmsPage, $cmsBlock);

        if ($cmsPage->hasPublishedStatus() && ! $cmsPage->hasPublishableContent($cmsBlock)) {
            return redirect()
                ->route('admin.cms-pages.show', $cmsPage)
                ->with('error', 'No se puede eliminar el último bloque de una página publicada. Pasa primero la página a borrador.');
        }

        $cmsBlock->delete();

        return redirect()
            ->route('admin.cms-pages.show', $cmsPage)
            ->with('success', 'Bloque CMS eliminado correctamente.');
    }

    private function ensureBlockBelongsToPage(CmsPage $cmsPage, CmsBlock $cmsBlock): void
    {
        abort_unless((int) $cmsBlock->cms_page_id === (int) $cmsPage->id, 404);
    }

    private function nextSortOrder(CmsPage $cmsPage): int
    {
        $maxSortOrder = $cmsPage->blocks()->max('sort_order');

        return $maxSortOrder === null ? 10 : (int) $maxSortOrder + 10;
    }

    /**
     * @return array<string, string>
     */
    private function typeOptions(): array
    {
        return [
            CmsBlockType::HEADING->value => 'Encabezado',
            CmsBlockType::TEXT->value => 'Texto',
            CmsBlockType::LIST->value => 'Lista',
            CmsBlockType::IMAGE->value => 'Imagen',
            CmsBlockType::GALLERY->value => 'Galería',
            CmsBlockType::BUTTON->value => 'Botón',
            CmsBlockType::DOCUMENT_LINK->value => 'Documento enlazado',
        ];
    }
}
