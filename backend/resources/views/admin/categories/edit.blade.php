@extends('admin.layout')

@section('title', 'Editar categoría')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Editar categoría</h1>
            <p class="text-secondary mb-0">Campeonato: {{ $championship->name }}</p>
        </div>

        <a href="{{ route('admin.championships.categories', $championship) }}"
           class="btn btn-outline-secondary">
            Volver al listado
        </a>
    </div>

    <div class="card page-card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.categories.update', $category) }}">
                @method('PUT')
                @include('admin.categories._form', ['submitLabel' => 'Guardar cambios'])
            </form>
        </div>
    </div>
@endsection
