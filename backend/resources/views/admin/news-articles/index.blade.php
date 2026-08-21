@extends('admin.layout')

@section('content')
    <div class="container-fluid px-0">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Noticias</h1>
                <p class="text-secondary mb-0">Contenido editorial cronológico, separado de las páginas CMS.</p>
            </div>
            <a href="{{ route('admin.news-articles.create') }}" class="btn btn-primary">Crear noticia</a>
        </div>

        @if (session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif

        @if ($articles->isEmpty())
            <div class="alert alert-info">No hay noticias registradas.</div>
        @else
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                            <tr>
                                <th scope="col">Título</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Fecha</th>
                                <th scope="col" class="text-center">Imagen</th>
                                <th scope="col" class="text-end">Acciones</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($articles as $article)
                                @php($state = $article->publicationState())
                                <tr>
                                    <td>
                                        <strong>{{ $article->title }}</strong>
                                        <div class="small text-secondary">/{{ $article->slug }}</div>
                                    </td>
                                    <td><span class="badge {{ $state->badgeClass() }}">{{ $state->label() }}</span></td>
                                    <td>{{ $article->published_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}</td>
                                    <td class="text-center">{{ $article->image_key ? 'Sí' : 'No' }}</td>
                                    <td>
                                        <div class="d-flex justify-content-end gap-2">
                                            <a
                                                href="{{ route('admin.news-articles.edit', $article) }}"
                                                class="btn btn-sm btn-outline-secondary"
                                            >
                                                Editar
                                            </a>
                                            <form
                                                method="POST"
                                                action="{{ route('admin.news-articles.destroy', $article) }}"
                                                onsubmit="return confirm('¿Eliminar esta noticia y su imagen? La URL pública dejará de existir.')"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="mt-3">{{ $articles->links() }}</div>
        @endif
    </div>
@endsection
