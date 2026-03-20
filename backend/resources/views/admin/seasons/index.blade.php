@extends('admin.layout')

@section('content')

    <div class="container mt-4">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Temporadas</h1>
                <p class="text-secondary mb-0">Gestión y acceso al ranking de temporada</p>
            </div>

            <a href="{{ route('admin.seasons.create') }}" class="btn btn-primary">
                Crear temporada
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

        @if ($seasons->isEmpty())
            <div class="alert alert-info">
                No hay temporadas registradas.
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
                                <th>Inicio</th>
                                <th>Fin</th>
                                <th>Estado</th>
                                <th class="text-center" style="width: 360px;">Acciones</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($seasons as $season)
                                <tr>
                                    <td>{{ $season->id }}</td>
                                    <td>{{ $season->name }}</td>
                                    <td>{{ $season->start_date?->format('d/m/Y') ?? '-' }}</td>
                                    <td>{{ $season->end_date?->format('d/m/Y') ?? '-' }}</td>
                                    <td>{{ $season->status?->value ?? $season->status }}</td>
                                    <td class="text-center">
                                        <div class="d-flex flex-wrap justify-content-center gap-2">
                                            <a href="{{ route('admin.seasons.show', $season) }}"
                                               class="btn btn-sm btn-outline-primary">
                                                Ver
                                            </a>

                                            <a href="{{ route('admin.seasons.edit', $season) }}"
                                               class="btn btn-sm btn-outline-secondary">
                                                Editar
                                            </a>

                                            <a href="{{ route('admin.seasons.championships', $season) }}"
                                               class="btn btn-sm btn-outline-info">
                                                Campeonatos
                                            </a>

                                            <form method="POST"
                                                  action="{{ route('admin.seasons.destroy', $season) }}"
                                                  onsubmit="return confirm('¿Eliminar temporada?')">
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

    </div>

@endsection
