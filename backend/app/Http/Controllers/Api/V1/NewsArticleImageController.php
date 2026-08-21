<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NewsArticle;
use App\Services\Media\Exceptions\MediaObjectNotFound;
use App\Services\Media\Exceptions\MediaStorageException;
use App\Services\Media\MediaDeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class NewsArticleImageController extends Controller
{
    public function __invoke(
        string $slug,
        MediaDeliveryService $delivery
    ): Response|RedirectResponse {
        $article = NewsArticle::query()
            ->effectivelyPublished()
            ->where('slug', $slug)
            ->firstOrFail();

        abort_unless(is_string($article->image_key), 404);

        try {
            return $delivery->deliverPublic($article->image_key);
        } catch (MediaObjectNotFound) {
            abort(404);
        } catch (MediaStorageException) {
            abort(503, 'La imagen de la noticia no está disponible temporalmente.');
        }
    }
}
