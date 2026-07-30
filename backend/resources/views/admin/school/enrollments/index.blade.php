@extends('admin.layout')

@section('content')
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Inscripciones de Escuela</h1>
                <p class="text-secondary mb-0">
                    Solicitudes, participantes activos y bajas con histórico
                </p>
            </div>

            <a href="{{ route('admin.school.enrollments.create') }}" class="btn btn-primary">
                Alta manual
            </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="row g-3 mb-4">
            @foreach ($statuses as $status)
                <div class="col-6 col-lg-3">
                    <div class="card page-card h-100">
                        <div class="card-body">
                            <div class="text-secondary">{{ $status->label() }}</div>
                            <div class="fs-3 fw-bold">{{ $counts[$status->value] }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card page-card mb-4">
            <div class="card-body">
                <form
                    method="GET"
                    action="{{ route('admin.school.enrollments.index') }}"
                    class="row g-3 align-items-end"
                >
                    <div class="col-md-4">
                        <label for="program_filter" class="form-label">Programa</label>
                        <select id="program_filter" name="program" class="form-select">
                            <option value="">Todos</option>
                            @foreach ($programs as $program)
                                <option
                                    value="{{ $program->id }}"
                                    @selected((string) ($filters['program'] ?? '') === (string) $program->id)
                                >
                                    {{ $program->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="level_filter" class="form-label">Nivel</label>
                        <select id="level_filter" name="level" class="form-select">
                            <option value="">Todos</option>
                            @foreach ($levels as $level)
                                <option
                                    value="{{ $level->id }}"
                                    @selected((string) ($filters['level'] ?? '') === (string) $level->id)
                                >
                                    {{ $level->program->name }} — {{ $level->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="status_filter" class="form-label">Estado</label>
                        <select id="status_filter" name="status" class="form-select">
                            <option value="">Todos</option>
                            @foreach ($statuses as $status)
                                <option
                                    value="{{ $status->value }}"
                                    @selected(($filters['status'] ?? '') === $status->value)
                                >
                                    {{ $status->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-outline-primary">Filtrar</button>
                        <a
                            href="{{ route('admin.school.enrollments.index') }}"
                            class="btn btn-outline-secondary"
                        >
                            Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </div>

        @if ($enrollments->isEmpty())
            <div class="alert alert-info">No hay inscripciones para este contexto.</div>
        @else
            <div class="card page-card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0">
                            <thead class="table-dark">
                            <tr>
                                <th>Solicitud</th>
                                <th>Participante</th>
                                <th>Programa</th>
                                <th>Nivel</th>
                                <th>Estado</th>
                                <th class="text-center">Acción</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($enrollments as $enrollment)
                                <tr>
                                    <td>
                                        <time datetime="{{ $enrollment->requested_at->toIso8601String() }}">
                                            {{ $enrollment->requested_at->format('d/m/Y H:i') }}
                                        </time>
                                    </td>
                                    <td>{{ $enrollment->participant_name }}</td>
                                    <td>{{ $enrollment->program->name }}</td>
                                    <td>{{ $enrollment->level?->name ?? 'Sin asignar' }}</td>
                                    <td>
                                        <span class="badge text-bg-secondary">
                                            {{ $enrollment->status->label() }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <a
                                            href="{{ route('admin.school.enrollments.show', $enrollment) }}"
                                            class="btn btn-sm btn-outline-primary"
                                        >
                                            Ver detalle
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        {{ $enrollments->links() }}
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
