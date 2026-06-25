@extends('admin.layout')

@section('content')

    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Editar bloque CMS</h1>
                <p class="text-secondary mb-0">{{ $page->title }}</p>
            </div>

            <a href="{{ route('admin.cms-pages.show', $page) }}" class="btn btn-outline-secondary">
                Volver
            </a>
        </div>

        <div class="card page-card">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.cms-pages.blocks.update', [$page, $block]) }}">
                    @method('PUT')
                    @include('admin.cms-blocks._form')
                </form>
            </div>
        </div>
    </div>

@endsection
