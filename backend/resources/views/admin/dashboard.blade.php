@extends('admin.layout')

@section('title', 'Dashboard')

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Dashboard</h1>
            <p class="text-secondary mb-0">
                Panel general de administración de Galotxas
            </p>
        </div>
    </div>

    <div class="card page-card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Bienvenido</h2>
            <p class="mb-0">
                Has iniciado sesión como <strong>{{ auth()->user()->name }}</strong>.
                Desde aquí puedes acceder a las principales áreas de gestión del sistema.
            </p>
        </div>
    </div>

    <div class="card page-card mb-4 border-warning">
        <div class="card-header bg-warning bg-opacity-10 text-warning-emphasis d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0">Solicitudes de inscripción pendientes</h2>
            @if($pendingRequests->isNotEmpty())
                <span class="badge bg-warning text-dark">{{ $pendingRequests->count() }} @if($pendingRequests->count() == 20) (últimas 20) @endif</span>
            @endif
        </div>
        <div class="card-body p-0">
            @if($pendingRequests->isEmpty())
                <div class="p-4 text-center text-secondary">
                    <p class="mb-0">No hay solicitudes pendientes de revisión.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Jugador</th>
                                <th>Campeonato</th>
                                <th>Categoría Sugerida</th>
                                <th class="text-end">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pendingRequests as $request)
                                <tr>
                                    <td>
                                        <div title="{{ $request->created_at->format('d/m/Y H:i') }}">
                                            {{ $request->created_at->diffForHumans() }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-medium">
                                            @if($request->player)
                                                {{ $request->player->nickname ?: $request->player->user->name . ' ' . $request->player->user->lastname }}
                                            @else
                                                {{ $request->user->name }} {{ $request->user->lastname }}
                                            @endif
                                        </div>
                                        <div class="small text-secondary">{{ $request->user->email }}</div>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.championships.show', $request->championship) }}" class="text-decoration-none text-body fw-medium">
                                            {{ $request->championship->name }}
                                        </a>
                                    </td>
                                    <td>
                                        @if($request->suggestedCategory)
                                            <span class="badge bg-light text-dark border">{{ $request->suggestedCategory->name }}</span>
                                        @else
                                            <span class="text-secondary small">Ninguna</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <form action="{{ route('admin.championships.registration-requests.approve', [$request->championship, $request]) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success" title="Aprobar solicitud">
                                                    Aprobar
                                                </button>
                                            </form>

                                            <form action="{{ route('admin.championships.registration-requests.reject', [$request->championship, $request]) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Seguro que deseas rechazar esta solicitud?');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-danger" title="Rechazar solicitud">
                                                    Rechazar
                                                </button>
                                            </form>

                                            <a href="{{ route('admin.championships.show', $request->championship) }}#solicitudes" class="btn btn-sm btn-outline-primary" title="Revisar campeonato">
                                                Revisar
                                            </a>
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

    <div class="card page-card mb-4 border-info">
        <div class="card-header bg-info bg-opacity-10 text-info-emphasis d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0">Solicitudes aprobadas pendientes de categoría</h2>
            @if($approvedUnassignedRequests->isNotEmpty())
                <span class="badge bg-info text-dark">{{ $approvedUnassignedRequests->count() }} @if($approvedUnassignedRequests->count() == 20) (últimas 20) @endif</span>
            @endif
        </div>
        <div class="card-body p-0">
            @if($approvedUnassignedRequests->isEmpty())
                <div class="p-4 text-center text-secondary">
                    <p class="mb-0">No hay solicitudes aprobadas pendientes de asignación a categoría.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Aprobada</th>
                                <th>Jugador</th>
                                <th>Campeonato</th>
                                <th>Categoría Sugerida</th>
                                <th class="text-end">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($approvedUnassignedRequests as $request)
                                <tr>
                                    <td>
                                        <div title="{{ $request->updated_at->format('d/m/Y H:i') }}">
                                            {{ $request->updated_at->diffForHumans() }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-medium">
                                            @if($request->player)
                                                {{ $request->player->nickname ?: $request->player->user->name . ' ' . $request->player->user->lastname }}
                                            @else
                                                {{ $request->user->name }} {{ $request->user->lastname }}
                                            @endif
                                        </div>
                                        <div class="small text-secondary">{{ $request->user->email }}</div>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.championships.show', $request->championship) }}" class="text-decoration-none text-body fw-medium">
                                            {{ $request->championship->name }}
                                        </a>
                                    </td>
                                    <td>
                                        @if($request->suggestedCategory)
                                            <span class="badge bg-light text-dark border">{{ $request->suggestedCategory->name }}</span>
                                        @else
                                            <span class="text-secondary small">Ninguna</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.championships.categories', $request->championship) }}" class="btn btn-sm btn-outline-primary" title="Ver categorías del campeonato">
                                            Asignar categoría
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6 col-xl-3">
            <div class="card page-card h-100">
                <div class="card-body d-flex flex-column">
                    <h2 class="h5 mb-2">Temporadas</h2>
                    <p class="text-secondary mb-4">
                        Consulta y gestiona las temporadas disponibles del sistema.
                    </p>

                    <div class="mt-auto">
                        <a href="{{ route('admin.seasons.index') }}" class="btn btn-primary w-100">
                            Ir a temporadas
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card page-card h-100">
                <div class="card-body d-flex flex-column">
                    <h2 class="h5 mb-2">Campeonatos</h2>
                    <p class="text-secondary mb-4">
                        Revisa campeonatos, rankings, solicitudes de inscripción y estados.
                    </p>

                    <div class="mt-auto">
                        <a href="{{ route('admin.championships.index') }}" class="btn btn-primary w-100">
                            Ir a campeonatos
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card page-card h-100">
                <div class="card-body d-flex flex-column">
                    <h2 class="h5 mb-2">Usuarios</h2>
                    <p class="text-secondary mb-4">
                        Administra usuarios, perfiles y acceso al sistema.
                    </p>

                    <div class="mt-auto">
                        <a href="{{ route('admin.users.index') }}" class="btn btn-primary w-100">
                            Ir a usuarios
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card page-card h-100">
                <div class="card-body d-flex flex-column">
                    <h2 class="h5 mb-2">Jugadores</h2>
                    <p class="text-secondary mb-4">
                        Accede a los perfiles deportivos de los jugadores registrados.
                    </p>

                    <div class="mt-auto">
                        <a href="{{ route('admin.players.index') }}" class="btn btn-primary w-100">
                            Ir a jugadores
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-lg-8">
            <div class="card page-card h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Áreas de gestión disponibles</h2>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="border rounded p-3 bg-light h-100">
                                <h3 class="h6 mb-2">Competición</h3>
                                <ul class="mb-0 ps-3">
                                    <li>Temporadas y campeonatos</li>
                                    <li>Categorías e inscripciones</li>
                                    <li>Generación de liga y copa</li>
                                    <li>Resultados y validaciones</li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="border rounded p-3 bg-light h-100">
                                <h3 class="h6 mb-2">Personas y acceso</h3>
                                <ul class="mb-0 ps-3">
                                    <li>Usuarios del sistema</li>
                                    <li>Perfiles de jugador</li>
                                    <li>Relación usuario / player</li>
                                    <li>Estados y permisos</li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="border rounded p-3 bg-light h-100">
                                <h3 class="h6 mb-2">Operativa deportiva</h3>
                                <ul class="mb-0 ps-3">
                                    <li>Partidos, calendario y pistas</li>
                                    <li>Reprogramaciones</li>
                                    <li>Conflictos y revisiones</li>
                                    <li>Seguimiento del estado competitivo</li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="border rounded p-3 bg-light h-100">
                                <h3 class="h6 mb-2">Rankings</h3>
                                <ul class="mb-0 ps-3">
                                    <li>Ranking por categoría</li>
                                    <li>Ranking por campeonato</li>
                                    <li>Ranking por temporada</li>
                                    <li>Ranking histórico</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card page-card h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Accesos rápidos</h2>

                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.seasons.index') }}" class="btn btn-outline-secondary">
                            Ver temporadas
                        </a>

                        <a href="{{ route('admin.championships.index') }}" class="btn btn-outline-secondary">
                            Ver campeonatos
                        </a>

                        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
                            Ver usuarios
                        </a>

                        <a href="{{ route('admin.players.index') }}" class="btn btn-outline-secondary">
                            Ver jugadores
                        </a>

                        <a href="{{ route('admin.rankings.history') }}" class="btn btn-outline-secondary">
                            Ranking histórico
                        </a>
                    </div>

                    <hr>

                    <div class="small text-secondary">
                        Usa este panel como punto de entrada a la gestión general.
                        Las tareas más específicas se realizan dentro de cada campeonato y categoría.
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
