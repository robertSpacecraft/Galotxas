@extends('admin.layout')

@section('content')
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Niveles de Escuela</h1>
                <p class="text-secondary mb-0">
                    Oferta formativa, rangos orientativos y visibilidad
                </p>
            </div>

            <a
                href="{{ route('admin.school.levels.create', ['program' => $selectedProgramId]) }}"
                class="btn btn-primary"
            >
                Crear nivel
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
                <form method="GET" action="{{ route('admin.school.levels.index') }}" class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label for="program_filter" class="form-label">Programa</label>
                        <select id="program_filter" name="program" class="form-select">
                            <option value="">Todos los programas</option>
                            @foreach ($programs as $program)
                                <option
                                    value="{{ $program->id }}"
                                    @selected($selectedProgramId === $program->id)
                                >
                                    {{ $program->name }}
                                    ({{ $program->is_public ? 'público' : 'privado' }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-outline-primary">Filtrar</button>
                        <a href="{{ route('admin.school.levels.index') }}" class="btn btn-outline-secondary">
                            Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </div>

        @if ($levels->isEmpty())
            <div class="alert alert-info">No hay niveles registrados para este contexto.</div>
        @else
            <div class="card page-card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0">
                            <thead class="table-dark">
                            <tr>
                                <th>Programa</th>
                                <th>Nombre</th>
                                <th>Edades</th>
                                <th>Operativo</th>
                                <th>Visibilidad</th>
                                <th>Efectivo</th>
                                <th class="text-end">Orden</th>
                                <th class="text-center" style="width: 250px;">Acciones</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($levels as $level)
                                <tr>
                                    <td>
                                        {{ $level->program->name }}
                                        <span class="badge {{ $level->program->is_public ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ $level->program->is_public ? 'Público' : 'Privado' }}
                                        </span>
                                    </td>
                                    <td>{{ $level->name }}</td>
                                    <td>
                                        @if ($level->minimum_age !== null || $level->maximum_age !== null)
                                            {{ $level->minimum_age ?? '–' }}–{{ $level->maximum_age ?? '–' }}
                                        @else
                                            Sin rango
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $level->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ $level->is_active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $level->is_public ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ $level->is_public ? 'Público' : 'Privado' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $level->isEffectivelyPublic() ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ $level->isEffectivelyPublic() ? 'Visible' : 'Oculto' }}
                                        </span>
                                    </td>
                                    <td class="text-end">{{ $level->sort_order }}</td>
                                    <td class="text-center">
                                        <div class="d-flex flex-wrap justify-content-center gap-2">
                                            <a
                                                href="{{ route('admin.school.schedules.index', ['level' => $level->id]) }}"
                                                class="btn btn-sm btn-outline-primary"
                                            >
                                                Horarios
                                            </a>
                                            <a
                                                href="{{ route('admin.school.levels.edit', $level) }}"
                                                class="btn btn-sm btn-outline-secondary"
                                            >
                                                Editar
                                            </a>

                                            @if ($level->schedules_count > 0 || $level->enrollments_count > 0)
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-danger"
                                                    disabled
                                                    title="El nivel tiene horarios o inscripciones asociadas"
                                                >
                                                    Eliminar
                                                </button>
                                            @else
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.school.levels.destroy', $level) }}"
                                                    onsubmit="return confirm('¿Eliminar este nivel?')"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
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
