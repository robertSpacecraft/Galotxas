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
            'page' => new CmsPage(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function store(StoreCmsPageRequest $request)
    {
        CmsPage::query()->create($this->normalizePageData($request->validated()));

        return redirect()
            ->route('admin.cms-pages.index')
            ->with('success', 'Página CMS creada correctamente.');
    }

    public function show(CmsPage $cmsPage)
    {
        $cmsPage->loadCount('blocks');

        return view('admin.cms-pages.show', [
            'page' => $cmsPage,
        ]);
    }

    public function edit(CmsPage $cmsPage)
    {
        return view('admin.cms-pages.edit', [
            'page' => $cmsPage,
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function update(UpdateCmsPageRequest $request, CmsPage $cmsPage)
    {
        $cmsPage->update($this->normalizePageData($request->validated()));

        return redirect()
            ->route('admin.cms-pages.index')
            ->with('success', 'Página CMS actualizada correctamente.');
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizePageData(array $data): array
    {
        if ($data['status'] === CmsPageStatus::PUBLISHED->value && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
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
