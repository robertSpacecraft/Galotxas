@extends('admin.layout')

@section('content')

    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Editar campeonato</h1>
                <p class="text-secondary mb-0">Temporada: {{ $championship->season->name }}</p>
            </div>

            <a href="{{ route('admin.seasons.championships', $championship->season) }}"
               class="btn btn-outline-secondary">
                Volver al listado
            </a>
        </div>

        <div class="card page-card">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.championships.update', $championship) }}">
                    @method('PUT')
                    @include('admin.championships._form', [
                        'submitLabel' => 'Guardar cambios',
                        'backSeason' => $championship->season,
                    ])
                </form>
            </div>
        </div>
    </div>

@endsection
