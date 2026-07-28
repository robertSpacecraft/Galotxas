@extends('admin.layout')

@section('content')
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Horarios de Escuela</h1>
                <p class="text-secondary mb-0">
                    Programación semanal por nivel y ubicación
                </p>
            </div>

            <a
                href="{{ route('admin.school.schedules.create', ['level' => $selectedLevelId]) }}"
                class="btn btn-primary"
            >
                Crear horario
            </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card page-card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.school.schedules.index') }}" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label for="program_filter" class="form-label">Programa</label>
                        <select id="program_filter" name="program" class="form-select">
                            <option value="">Todos los programas</option>
                            @foreach ($programs as $program)
                                <option
                                    value="{{ $program->id }}"
                                    @selected($selectedProgramId === $program->id)
                                >
                                    {{ $program->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label for="level_filter" class="form-label">Nivel</label>
                        <select id="level_filter" name="level" class="form-select">
                            <option value="">Todos los niveles</option>
                            @foreach ($allLevels as $level)
                                <option
                                    value="{{ $level->id }}"
                                    @selected($selectedLevelId === $level->id)
                                >
                                    {{ $level->program->name }} — {{ $level->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-outline-primary">Filtrar</button>
                        <a href="{{ route('admin.school.schedules.index') }}" class="btn btn-outline-secondary">
                            Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </div>

        @if ($schedules->isEmpty())
            <div class="alert alert-info">No hay horarios registrados para este contexto.</div>
        @else
            <div class="card page-card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0">
                            <thead class="table-dark">
                            <tr>
                                <th>Programa</th>
                                <th>Nivel</th>
                                <th>Día</th>
                                <th>Horario</th>
                                <th>Ubicación</th>
                                <th>Estado</th>
                                <th>Efectivo</th>
                                <th class="text-end">Orden</th>
                                <th class="text-center" style="width: 180px;">Acciones</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($schedules as $schedule)
                                <tr>
                                    <td>{{ $schedule->level->program->name }}</td>
                                    <td>{{ $schedule->level->name }}</td>
                                    <td>
                                        {{ $schedule->day_of_week->label() }}
                                        <span class="text-secondary">({{ $schedule->day_of_week->value }})</span>
                                    </td>
                                    <td>
                                        {{ $schedule->startsAtLabel() }}–{{ $schedule->endsAtLabel() }}
                                    </td>
                                    <td>
                                        {{ $schedule->location->name }} — {{ $schedule->location->locality }}
                                        <span class="badge {{ $schedule->location->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ $schedule->location->is_active ? 'Activa' : 'Inactiva' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $schedule->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ $schedule->is_active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $schedule->isEffectivelyPublic() ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ $schedule->isEffectivelyPublic() ? 'Visible' : 'Oculto' }}
                                        </span>
                                    </td>
                                    <td class="text-end">{{ $schedule->sort_order }}</td>
                                    <td class="text-center">
                                        <div class="d-flex flex-wrap justify-content-center gap-2">
                                            <a
                                                href="{{ route('admin.school.schedules.edit', $schedule) }}"
                                                class="btn btn-sm btn-outline-secondary"
                                            >
                                                Editar
                                            </a>
                                            <form
                                                method="POST"
                                                action="{{ route('admin.school.schedules.destroy', $schedule) }}"
                                                onsubmit="return confirm('¿Eliminar este horario?')"
                                            >
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
