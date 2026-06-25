@extends('admin.layout')

@section('content')

    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Páginas CMS</h1>
                <p class="text-secondary mb-0">Gestión básica de páginas públicas</p>
            </div>

            <a href="{{ route('admin.cms-pages.create') }}" class="btn btn-primary">
                Crear página
            </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if ($pages->isEmpty())
            <div class="alert alert-info">
                No hay páginas CMS registradas.
            </div>
        @else
            <div class="card page-card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0">
                            <thead class="table-dark">
                            <tr>
                                <th>Título</th>
                                <th>Slug</th>
                                <th>Estado</th>
                                <th>Publicación</th>
                                <th class="text-end">Bloques</th>
                                <th class="text-center" style="width: 180px;">Acciones</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($pages as $page)
                                @php
                                    $status = $page->status?->value ?? $page->status;
                                    $statusClass = $status === 'published' ? 'text-bg-success' : 'text-bg-secondary';
                                    $statusLabel = $status === 'published' ? 'Publicada' : 'Borrador';
                                @endphp
                                <tr>
                                    <td>{{ $page->title }}</td>
                                    <td><code>{{ $page->slug }}</code></td>
                                    <td>
                                        <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                                    </td>
                                    <td>{{ $page->published_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td class="text-end">{{ $page->blocks_count }}</td>
                                    <td class="text-center">
                                        <div class="d-flex flex-wrap justify-content-center gap-2">
                                            <a href="{{ route('admin.cms-pages.show', $page) }}"
                                               class="btn btn-sm btn-outline-primary">
                                                Ver
                                            </a>
                                            <a href="{{ route('admin.cms-pages.edit', $page) }}"
                                               class="btn btn-sm btn-outline-secondary">
                                                Editar
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>

@endsection
