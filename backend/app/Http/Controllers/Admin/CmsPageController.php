<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CmsPageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCmsPageRequest;
use App\Http\Requests\Admin\UpdateCmsPageRequest;
use App\Models\CmsPage;

class CmsPageController extends Controller
{
    public function index()
    {
        $pages = CmsPage::query()
            ->withCount('blocks')
            ->orderByDesc('id')
            ->get();

        return view('admin.cms-pages.index', [
            'pages' => $pages,
        ]);
    }

    public function create()
    {
        return view('admin.cms-pages.create', [
            'page' => new CmsPage,
            'hasPublishableContent' => false,
        ]);
    }

    public function store(StoreCmsPageRequest $request)
    {
        CmsPage::query()->create($request->validated());

        return redirect()
            ->route('admin.cms-pages.index')
            ->with('success', 'Página CMS creada correctamente.');
    }

    public function show(CmsPage $cmsPage)
    {
        $cmsPage->load(['blocks']);
        $cmsPage->loadCount('blocks');

        return view('admin.cms-pages.show', [
            'page' => $cmsPage,
            'appTimezone' => config('app.timezone'),
        ]);
    }

    public function edit(CmsPage $cmsPage)
    {
        return view('admin.cms-pages.edit', [
            'page' => $cmsPage,
            'statusOptions' => $this->statusOptions(),
            'appTimezone' => config('app.timezone'),
            'hasPublishableContent' => $cmsPage->hasPublishableContent(),
        ]);
    }

    public function update(UpdateCmsPageRequest $request, CmsPage $cmsPage)
    {
        $cmsPage->update($request->validated());

        return redirect()
            ->route('admin.cms-pages.index')
            ->with('success', 'Página CMS actualizada correctamente.');
    }

    /**
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        return [
            CmsPageStatus::DRAFT->value => 'Borrador',
            CmsPageStatus::PUBLISHED->value => 'Publicada',
        ];
    }
}
