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

        <div class="card page-card">
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

        <div class="alert alert-info mt-4 mb-0">
            La gestión de bloques se implementará en CMS-3.
        </div>
    </div>

@endsection
