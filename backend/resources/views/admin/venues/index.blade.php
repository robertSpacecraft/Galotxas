@extends('admin.layout')

@section('content')
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Pistas</h1>
                <p class="text-secondary mb-0">Configuración de espacios para partidos y calendarios</p>
            </div>

            <a href="{{ route('admin.venues.create') }}" class="btn btn-primary">Crear pista</a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if ($venues->isEmpty())
            <div class="alert alert-info">No hay pistas registradas.</div>
        @else
            <div class="card page-card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0">
                            <thead class="table-dark">
                            <tr>
                                <th>Nombre</th>
                                <th>Ubicación</th>
                                <th>Descripción</th>
                                <th class="text-end">Partidos</th>
                                <th class="text-center" style="width: 210px;">Acciones</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($venues as $venue)
                                @php
                                    $isInUse = $venue->matches_count > 0 || $venue->reschedule_requests_count > 0;
                                @endphp
                                <tr>
                                    <td>{{ $venue->name }}</td>
                                    <td>{{ $venue->location ?: '-' }}</td>
                                    <td>{{ $venue->description ?: '-' }}</td>
                                    <td class="text-end">{{ $venue->matches_count }}</td>
                                    <td class="text-center">
                                        <div class="d-flex flex-wrap justify-content-center gap-2">
                                            <a href="{{ route('admin.venues.edit', $venue) }}"
                                               class="btn btn-sm btn-outline-secondary">
                                                Editar
                                            </a>

                                            @if ($isInUse)
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-danger"
                                                        disabled
                                                        title="La pista está asociada a partidos o solicitudes de reprogramación">
                                                    Eliminar
                                                </button>
                                            @else
                                                <form method="POST" action="{{ route('admin.venues.destroy', $venue) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="btn btn-sm btn-outline-danger"
                                                            onclick="return confirm('¿Eliminar esta pista?')">
                                                        Eliminar
                                                    </button>
                                                </form>
                                            @endif
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
