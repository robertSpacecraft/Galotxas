@extends('admin.layout')

@section('content')

    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">{{ $page->title }}</h1>
                <p class="text-secondary mb-0">Detalle administrativo de página CMS</p>
            </div>

            <div class="d-flex gap-2">
                <a href="{{ route('admin.cms-pages.edit', $page) }}" class="btn btn-primary">
                    Editar
                </a>
                <a href="{{ route('admin.cms-pages.blocks.create', $page) }}" class="btn btn-outline-primary">
                    Crear bloque
                </a>
                <a href="{{ route('admin.cms-pages.index') }}" class="btn btn-outline-secondary">
                    Volver
                </a>
            </div>
        </div>

        @php
            $status = $page->status?->value ?? $page->status;
            $statusClass = $status === 'published' ? 'text-bg-success' : 'text-bg-secondary';
            $statusLabel = $status === 'published' ? 'Publicada' : 'Borrador';
        @endphp

        <div class="card page-card mb-4">
            <div class="card-header fw-semibold">
                Datos de publicación
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Slug</dt>
                    <dd class="col-sm-9"><code>{{ $page->slug }}</code></dd>

                    <dt class="col-sm-3">Estado</dt>
                    <dd class="col-sm-9">
                        <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                    </dd>

                    <dt class="col-sm-3">Fecha de publicación</dt>
                    <dd class="col-sm-9">{{ $page->published_at?->format('d/m/Y H:i') ?? '-' }}</dd>

                    <dt class="col-sm-3">Bloques</dt>
                    <dd class="col-sm-9">{{ $page->blocks_count }}</dd>
                </dl>
            </div>
        </div>

        <div class="card page-card mb-4">
            <div class="card-header fw-semibold">
                SEO
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Título SEO</dt>
                    <dd class="col-sm-9">{{ $page->seo_title ?? '-' }}</dd>

                    <dt class="col-sm-3">Descripción SEO</dt>
                    <dd class="col-sm-9">{{ $page->seo_description ?? '-' }}</dd>
                </dl>
            </div>
        </div>

        <div class="card page-card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="fw-semibold">Bloques</span>
                <a href="{{ route('admin.cms-pages.blocks.create', $page) }}" class="btn btn-sm btn-primary">
                    Crear bloque
                </a>
            </div>
            <div class="card-body">
                @if ($page->blocks->isEmpty())
                    <div class="alert alert-info mb-0">
                        Esta página todavía no tiene bloques.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0">
                            <thead class="table-dark">
                            <tr>
                                <th class="text-end" style="width: 90px;">Orden</th>
                                <th style="width: 180px;">Tipo</th>
                                <th>Resumen</th>
                                <th class="text-center" style="width: 180px;">Acciones</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($page->blocks as $block)
                                @php
                                    $type = $block->type?->value ?? $block->type;
                                    $data = $block->data ?? [];
                                    $summary = match ($type) {
                                        'heading', 'text' => $data['text'] ?? '',
                                        'list' => implode(', ', array_slice($data['items'] ?? [], 0, 3)),
                                        'image' => $data['url'] ?? '',
                                        'gallery' => implode(', ', array_slice($data['urls'] ?? [], 0, 3)),
                                        'button', 'document_link' => trim(($data['label'] ?? '').' '.($data['url'] ?? '')),
                                        default => '',
                                    };
                                    $typeLabel = [
                                        'heading' => 'Encabezado',
                                        'text' => 'Texto',
                                        'list' => 'Lista',
                                        'image' => 'Imagen',
                                        'gallery' => 'Galería',
                                        'button' => 'Botón',
                                        'document_link' => 'Documento',
                                    ][$type] ?? $type;
                                @endphp
                                <tr>
                                    <td class="text-end">{{ $block->sort_order }}</td>
                                    <td>{{ $typeLabel }}</td>
                                    <td>{{ $summary !== '' ? $summary : '-' }}</td>
                                    <td class="text-center">
                                        <div class="d-flex flex-wrap justify-content-center gap-2">
                                            <a href="{{ route('admin.cms-pages.blocks.edit', [$page, $block]) }}"
                                               class="btn btn-sm btn-outline-secondary">
                                                Editar
                                            </a>
                                            <form
                                                method="POST"
                                                action="{{ route('admin.cms-pages.blocks.destroy', [$page, $block]) }}"
                                                onsubmit="return confirm('¿Eliminar bloque CMS?')"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    Eliminar
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

@endsection
