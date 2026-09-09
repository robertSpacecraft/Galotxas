<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NewsArticle;
use App\Services\Media\Exceptions\MediaObjectNotFound;
use App\Services\Media\Exceptions\MediaStorageException;
use App\Services\Media\MediaDeliveryService;
use App\Services\Media\ResponsiveImageProfile;
use App\Services\Media\ResponsiveMediaResolver;
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

    public function variant(
        string $slug,
        int $width,
        ResponsiveMediaResolver $resolver,
        MediaDeliveryService $delivery,
    ): Response|RedirectResponse {
        $article = NewsArticle::query()
            ->effectivelyPublished()
            ->where('slug', $slug)
            ->firstOrFail();
        $variant = $resolver->variant($article->image_key, ResponsiveImageProfile::NewsCover, $width);
        abort_unless($variant !== null, 404);

        try {
            return $delivery->deliverPublicVariant($variant);
        } catch (MediaObjectNotFound) {
            abort(404);
        } catch (MediaStorageException) {
            abort(503, 'La imagen de la noticia no está disponible temporalmente.');
        }
    }
}
