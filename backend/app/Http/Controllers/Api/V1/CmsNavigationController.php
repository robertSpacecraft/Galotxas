<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicCmsNavigationItemResource;
use App\Models\CmsNavigationItem;
use Illuminate\Http\JsonResponse;

class CmsNavigationController extends Controller
{
    use ApiResponse;

    public function __invoke(): JsonResponse
    {
        $items = CmsNavigationItem::query()
            ->with('cmsPage')
            ->publiclyVisible()
            ->ordered()
            ->get()
            ->filter(fn (CmsNavigationItem $item): bool => CmsNavigationItem::isValidLabel($item->label))
            ->values();

        return $this->successResponse(
            PublicCmsNavigationItemResource::collection($items)
        )->header('Cache-Control', 'no-store');
    }
}
