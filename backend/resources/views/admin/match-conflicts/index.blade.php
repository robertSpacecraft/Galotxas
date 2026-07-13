@extends('admin.layout')

@section('title', 'Conflictos de resultados')

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Conflictos de resultados</h1>
            <p class="text-secondary mb-0">
                Partidos en revisión que requieren establecer un tanteo oficial.
            </p>
        </div>

        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">Volver al dashboard</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success" role="alert">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    @if ($matches->isEmpty())
        <div class="alert alert-info" role="status">
            No hay conflictos de resultados pendientes de resolución.
        </div>
    @else
        <div class="card page-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <caption class="visually-hidden">Partidos con discrepancias de resultados pendientes</caption>
                        <thead class="table-dark">
                            <tr>
                                <th scope="col">Competición</th>
                                <th scope="col">Participantes</th>
                                <th scope="col">Fecha y pista</th>
                                <th scope="col">Reporte local</th>
                                <th scope="col">Reporte visitante</th>
                                <th scope="col">Estado</th>
                                <th scope="col" class="text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($matches as $match)
                                <tr>
                                    <td>
                                        <strong>{{ $match->round?->category?->championship?->name ?: 'Campeonato' }}</strong>
                                        <div class="small text-secondary">
                                            {{ $match->round?->category?->name ?: 'Categoría' }}
                                            · {{ $match->round?->name ?: 'Jornada' }}
                                        </div>
                                    </td>
                                    <td>
                                        <div>@include('admin.match-conflicts._entry-name', ['entry' => $match->homeEntry])</div>
                                        <div class="small text-secondary">vs.</div>
                                        <div>@include('admin.match-conflicts._entry-name', ['entry' => $match->awayEntry])</div>
                                    </td>
                                    <td>
                                        <div>{{ $match->scheduled_date?->format('d/m/Y H:i') ?: 'Sin fecha' }}</div>
                                        <div class="small text-secondary">{{ $match->venue?->name ?: 'Sin pista' }}</div>
                                    </td>
                                    <td style="min-width: 220px;">
                                        @include('admin.match-conflicts._report', ['report' => $match->homeResultReport])
                                    </td>
                                    <td style="min-width: 220px;">
                                        @include('admin.match-conflicts._report', ['report' => $match->awayResultReport])
                                    </td>
                                    <td><span class="badge text-bg-info">En revisión</span></td>
                                    <td class="text-center">
                                        <a
                                            href="{{ route('admin.match-conflicts.show', $match) }}"
                                            class="btn btn-sm btn-primary"
                                        >
                                            Revisar y resolver
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endsection
