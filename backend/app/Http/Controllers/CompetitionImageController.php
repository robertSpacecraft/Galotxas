<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Championship;
use App\Models\Season;
use App\Services\CompetitionImageService;
use App\Services\Media\Exceptions\MediaObjectNotFound;
use App\Services\Media\Exceptions\MediaStorageException;
use App\Services\Media\MediaDeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CompetitionImageController extends Controller
{
    public function __construct(
        private readonly CompetitionImageService $covers,
        private readonly MediaDeliveryService $delivery,
    ) {}

    public function season(Request $request, Season $season): Response|RedirectResponse
    {
        return $this->deliver($request, $season);
    }

    public function championship(Request $request, Championship $championship): Response|RedirectResponse
    {
        return $this->deliver($request, $championship);
    }

    public function category(Request $request, Category $category): Response|RedirectResponse
    {
        return $this->deliver($request, $category);
    }

    private function deliver(Request $request, Season|Championship|Category $entity): Response|RedirectResponse
    {
        // Only the routes protected by auth + IsAdmin may preview private competition images.
        $admin = $request->routeIs('admin.*');
        abort_unless($admin || $entity->isEffectivelyPublic(), 404);
        abort_unless($this->covers->isManaged($entity->image_path), 404);

        try {
            return $admin
                ? $this->delivery->deliver($entity->image_path, privateTemporaryUrl: true)
                : $this->delivery->deliverPublic($entity->image_path);
        } catch (MediaObjectNotFound) {
            abort(404);
        } catch (MediaStorageException) {
            abort(503, 'No se pudo entregar la imagen. Inténtalo de nuevo más tarde.');
        }
    }
}
