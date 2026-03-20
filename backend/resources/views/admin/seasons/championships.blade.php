@extends('admin.layout')

@section('content')

    <div class="container mt-4">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Campeonatos de la temporada: {{ $season->name }}</h1>
                <p class="text-secondary mb-0">Listado de campeonatos asociados a esta temporada</p>
            </div>

            <a href="{{ route('admin.championships.create', $season) }}" class="btn btn-primary">
                Crear nuevo campeonato
            </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif

        @if ($championships->isEmpty())
            <div class="alert alert-info">
                No hay campeonatos registrados para esta temporada.
            </div>
        @else
            <div class="card page-card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0">
                            <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th class="text-center" style="width: 340px;">Acciones</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($championships as $championship)
                                <tr>
                                    <td>{{ $championship->id }}</td>
                                    <td>{{ $championship->name }}</td>
                                    <td>{{ $championship->type?->value ?? $championship->type }}</td>
                                    <td class="text-center">
                                        <div class="d-flex flex-wrap justify-content-center gap-2">
                                            <a href="{{ route('admin.championships.show', $championship) }}"
                                               class="btn btn-sm btn-outline-primary">
                                                Ver
                                            </a>

                                            <a href="{{ route('admin.championships.edit', $championship) }}"
                                               class="btn btn-sm btn-outline-secondary">
                                                Editar
                                            </a>

                                            <a href="{{ route('admin.championships.categories', $championship) }}"
                                               class="btn btn-sm btn-outline-info">
                                                Categorías
                                            </a>

                                            <form method="POST"
                                                  action="{{ route('admin.championships.destroy', $championship) }}"
                                                  onsubmit="return confirm('¿Eliminar campeonato?')">
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
                </div>
            </div>
        @endif

        <div class="mt-4">
            <a href="{{ route('admin.seasons.index') }}" class="btn btn-outline-secondary">
                Volver a temporadas
            </a>
        </div>

    </div>

@endsection
