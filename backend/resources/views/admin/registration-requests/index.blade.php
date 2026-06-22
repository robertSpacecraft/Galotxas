@extends('admin.layout')

@section('title', 'Solicitudes e inscripciones')

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Solicitudes e inscripciones</h1>
            <p class="text-secondary mb-0">
                Revisa solicitudes pendientes y asigna categorías a jugadores aprobados.
            </p>
        </div>

        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">
            Volver al dashboard
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

    <div class="card page-card mb-4 border-warning">
        <div class="card-header bg-warning bg-opacity-10 text-warning-emphasis d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0">Solicitudes de inscripción pendientes</h2>
            @if($pendingRequests->isNotEmpty())
                <span class="badge bg-warning text-dark">
                    {{ $pendingRequests->count() }} @if($pendingRequests->count() === 20) (últimas 20) @endif
                </span>
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
                                <th>Categoría sugerida</th>
                                <th class="text-end">Acciones</th>
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
                                            <form action="{{ route('admin.championships.registration-requests.approve', [$request->championship, $request]) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success">Aprobar</button>
                                            </form>

                                            <form action="{{ route('admin.championships.registration-requests.reject', [$request->championship, $request]) }}" method="POST" onsubmit="return confirm('¿Seguro que deseas rechazar esta solicitud?');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Rechazar</button>
                                            </form>

                                            <a href="{{ route('admin.championships.show', $request->championship) }}#solicitudes" class="btn btn-sm btn-outline-primary">
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
                <span class="badge bg-info text-dark">
                    {{ $approvedUnassignedRequests->count() }} @if($approvedUnassignedRequests->count() === 20) (últimas 20) @endif
                </span>
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
                                <th>Categoría sugerida</th>
                                <th>Asignación</th>
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
                                            {{ $request->player->nickname ?: $request->player->user->name . ' ' . $request->player->user->lastname }}
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
                                    <td>
                                        @php
                                            $categories = $request->championship->categories;
                                            $selectedCategory = $categories->firstWhere('id', $request->suggested_category_id)
                                                ?? $categories->first();
                                        @endphp

                                        @if($selectedCategory)
                                            <form
                                                action="{{ route('admin.categories.registrations.store', $selectedCategory) }}"
                                                method="POST"
                                                class="d-flex flex-column flex-xl-row gap-2 align-items-xl-center"
                                            >
                                                @csrf
                                                <input type="hidden" name="player_id" value="{{ $request->player_id }}">

                                                <label for="category-{{ $request->id }}" class="visually-hidden">
                                                    Categoría para {{ $request->player->nickname ?: $request->user->name }}
                                                </label>
                                                <select
                                                    id="category-{{ $request->id }}"
                                                    class="form-select form-select-sm"
                                                    onchange="this.form.action = this.value"
                                                    required
                                                >
                                                    @foreach($categories as $category)
                                                        <option
                                                            value="{{ route('admin.categories.registrations.store', $category) }}"
                                                            @selected($selectedCategory->is($category))
                                                        >
                                                            {{ $category->name }}
                                                        </option>
                                                    @endforeach
                                                </select>

                                                <button type="submit" class="btn btn-sm btn-primary flex-shrink-0">
                                                    Asignar
                                                </button>
                                            </form>
                                        @else
                                            <div class="small text-secondary mb-2">
                                                Este campeonato no tiene categorías disponibles.
                                            </div>
                                            <a href="{{ route('admin.categories.create', $request->championship) }}" class="btn btn-sm btn-outline-primary">
                                                Crear categoría
                                            </a>
                                        @endif

                                        <div class="mt-2">
                                            <a href="{{ route('admin.championships.categories', $request->championship) }}" class="small">
                                                Gestionar categorías
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
@endsection
