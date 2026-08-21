<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NewsArticleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreNewsArticleRequest;
use App\Http\Requests\Admin\UpdateNewsArticleRequest;
use App\Models\NewsArticle;
use App\Services\Media\Exceptions\InvalidMediaImage;
use App\Services\Media\Exceptions\MediaObjectNotFound;
use App\Services\Media\Exceptions\MediaStorageException;
use App\Services\Media\MediaDeliveryService;
use App\Services\NewsArticleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class NewsArticleController extends Controller
{
    public function index()
    {
        return view('admin.news-articles.index', [
            'articles' => NewsArticle::query()
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate(25),
        ]);
    }

    public function create()
    {
        return view('admin.news-articles.create', [
            'article' => new NewsArticle([
                'status' => NewsArticleStatus::DRAFT,
            ]),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function store(
        StoreNewsArticleRequest $request,
        NewsArticleService $service
    ): RedirectResponse {
        $validated = $request->validated();
        $image = $request->file('image');
        unset($validated['image']);

        try {
            $article = $service->create($validated, $image, $request->user());
        } catch (InvalidMediaImage|MediaStorageException $exception) {
            throw ValidationException::withMessages([
                'image' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.news-articles.edit', $article)
            ->with('success', 'Noticia creada como borrador.');
    }

    public function edit(NewsArticle $newsArticle)
    {
        $newsArticle->load('rightsConfirmedBy');

        return view('admin.news-articles.edit', [
            'article' => $newsArticle,
            'statusOptions' => $this->statusOptions(),
            'appTimezone' => config('app.timezone'),
        ]);
    }

    public function update(
        UpdateNewsArticleRequest $request,
        NewsArticle $newsArticle,
        NewsArticleService $service
    ): RedirectResponse {
        $validated = $request->validated();
        $image = $request->file('image');
        unset($validated['image']);

        try {
            $service->update($newsArticle, $validated, $image, $request->user());
        } catch (InvalidMediaImage|MediaStorageException $exception) {
            throw ValidationException::withMessages([
                'image' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.news-articles.edit', $newsArticle)
            ->with('success', 'Noticia actualizada correctamente.');
    }

    public function destroy(
        NewsArticle $newsArticle,
        NewsArticleService $service
    ): RedirectResponse {
        $service->delete($newsArticle);

        return redirect()
            ->route('admin.news-articles.index')
            ->with('success', 'Noticia eliminada correctamente.');
    }

    public function image(
        NewsArticle $newsArticle,
        MediaDeliveryService $delivery
    ): Response|RedirectResponse {
        abort_unless(is_string($newsArticle->image_key), 404);

        try {
            return $delivery->deliver($newsArticle->image_key, privateTemporaryUrl: true);
        } catch (MediaObjectNotFound) {
            abort(404);
        } catch (MediaStorageException) {
            abort(503, 'El recurso multimedia no está disponible temporalmente.');
        }
    }

    /**
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        return [
            NewsArticleStatus::DRAFT->value => 'Borrador',
            NewsArticleStatus::PUBLISHED->value => 'Publicada o programada',
        ];
    }
}
