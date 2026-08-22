<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CmsNavigationSlot;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCmsNavigationItemRequest;
use App\Http\Requests\Admin\UpdateCmsNavigationItemRequest;
use App\Models\CmsNavigationItem;
use App\Models\CmsPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CmsNavigationItemController extends Controller
{
    public function index(): View
    {
        return view('admin.cms-navigation.index', [
            'items' => CmsNavigationItem::query()->with('cmsPage')->ordered()->get(),
            'structuralItems' => ['Quiénes somos', 'Contacto', 'Federarse', 'Documentos'],
        ]);
    }

    public function create(): View
    {
        return view('admin.cms-navigation.create', [
            'item' => new CmsNavigationItem([
                'slot' => CmsNavigationSlot::CLUB,
                'sort_order' => 0,
                'is_active' => false,
            ]),
            'pages' => $this->eligiblePages(),
        ]);
    }

    public function store(StoreCmsNavigationItemRequest $request): RedirectResponse
    {
        CmsNavigationItem::query()->create([
            ...$request->validated(),
            'slot' => CmsNavigationSlot::CLUB->value,
        ]);

        return redirect()
            ->route('admin.cms-navigation.index')
            ->with('success', 'Elemento de navegación CMS creado correctamente.');
    }

    public function edit(CmsNavigationItem $cmsNavigationItem): View
    {
        $cmsNavigationItem->load('cmsPage');

        return view('admin.cms-navigation.edit', [
            'item' => $cmsNavigationItem,
            'pages' => $this->eligiblePages($cmsNavigationItem),
        ]);
    }

    public function update(
        UpdateCmsNavigationItemRequest $request,
        CmsNavigationItem $cmsNavigationItem
    ): RedirectResponse {
        $cmsNavigationItem->update([
            ...$request->validated(),
            'slot' => CmsNavigationSlot::CLUB->value,
        ]);

        return redirect()
            ->route('admin.cms-navigation.index')
            ->with('success', 'Elemento de navegación CMS actualizado correctamente.');
    }

    public function destroy(CmsNavigationItem $cmsNavigationItem): RedirectResponse
    {
        $cmsNavigationItem->delete();

        return redirect()
            ->route('admin.cms-navigation.index')
            ->with('success', 'Elemento de navegación CMS eliminado correctamente.');
    }

    /** @return Collection<int, CmsPage> */
    private function eligiblePages(?CmsNavigationItem $current = null): Collection
    {
        return CmsPage::query()
            ->whereNotIn('slug', CmsNavigationItem::RESERVED_PAGE_SLUGS)
            ->whereDoesntHave('navigationItems', function (Builder $items) use ($current): void {
                $items->where('slot', CmsNavigationSlot::CLUB->value);

                if ($current !== null) {
                    $items->where('id', '!=', $current->id);
                }
            })
            ->orderBy('title')
            ->orderBy('id')
            ->get();
    }
}
