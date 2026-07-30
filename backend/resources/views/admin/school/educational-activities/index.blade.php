@extends('admin.layout')

@section('content')
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Actividades con centros</h1>
                <p class="text-secondary mb-0">
                    Planificación e histórico de actuaciones educativas
                </p>
            </div>

            <a
                href="{{ route('admin.school.educational-activities.create') }}"
                class="btn btn-primary"
            >
                Crear actividad
            </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card page-card mb-4">
            <div class="card-body">
                <form
                    method="GET"
                    action="{{ route('admin.school.educational-activities.index') }}"
                    class="row g-3 align-items-end"
                >
                    <div class="col-lg-3 col-md-6">
                        <label for="center" class="form-label">Centro</label>
                        <select id="center" name="center" class="form-select">
                            <option value="">Todos</option>
                            @foreach ($centers as $center)
                                <option
                                    value="{{ $center->id }}"
                                    @selected((string) ($filters['center'] ?? '') === (string) $center->id)
                                >
                                    {{ $center->locality }} — {{ $center->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-6">
                        <label for="status" class="form-label">Estado</label>
                        <select id="status" name="status" class="form-select">
                            <option value="">Todos</option>
                            @foreach ($statuses as $status)
                                <option
                                    value="{{ $status->value }}"
                                    @selected(($filters['status'] ?? null) === $status->value)
                                >
                                    {{ $status->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-6">
                        <label for="date_from" class="form-label">Desde</label>
                        <input
                            id="date_from"
                            type="date"
                            name="date_from"
                            value="{{ $filters['date_from'] ?? '' }}"
                            class="form-control"
                        >
                    </div>

                    <div class="col-lg-2 col-md-6">
                        <label for="date_to" class="form-label">Hasta</label>
                        <input
                            id="date_to"
                            type="date"
                            name="date_to"
                            value="{{ $filters['date_to'] ?? '' }}"
                            class="form-control"
                        >
                    </div>

                    <div class="col-lg-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                        <a
                            href="{{ route('admin.school.educational-activities.index') }}"
                            class="btn btn-outline-secondary"
                        >
                            Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </div>

        @if ($activities->isEmpty())
            <div class="alert alert-info">
                No hay actividades para los filtros seleccionados.
            </div>
        @else
            <div class="card page-card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0">
                            <thead class="table-dark">
                            <tr>
                                <th>Fecha</th>
                                <th>Actividad</th>
                                <th>Centro</th>
                                <th>Horario</th>
                                <th>Ubicación</th>
                                <th class="text-end">Alumnado</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($activities as $activity)
                                <tr>
                                    <td>{{ $activity->activity_date->format('d/m/Y') }}</td>
                                    <td>{{ $activity->name }}</td>
                                    <td>
                                        {{ $activity->center->name }}
                                        <div class="small text-secondary">
                                            {{ $activity->center->locality }}
                                        </div>
                                    </td>
                                    <td>
                                        {{ $activity->starts_at
                                            ? $activity->startsAtLabel().'–'.$activity->endsAtLabel()
                                            : '-' }}
                                    </td>
                                    <td>{{ $activity->location?->name ?: '-' }}</td>
                                    <td class="text-end">
                                        {{ $activity->expected_students ?: '-' }}
                                    </td>
                                    <td>{{ $activity->status->label() }}</td>
                                    <td>
                                        <a
                                            href="{{ route('admin.school.educational-activities.show', $activity) }}"
                                            class="btn btn-sm btn-outline-primary"
                                        >
                                            Ver
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                {{ $activities->links() }}
            </div>
        @endif
    </div>
@endsection
