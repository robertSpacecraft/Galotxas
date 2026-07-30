@extends('admin.layout')

@section('content')
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Programa de Escuela</h1>
                <p class="text-secondary mb-0">
                    Configuración operativa de la Escuela permanente
                </p>
            </div>

            <a href="{{ route('admin.school.programs.create') }}" class="btn btn-primary">
                Crear programa
            </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if ($programs->isEmpty())
            <div class="alert alert-info">No hay programas de Escuela registrados.</div>
        @else
            <div class="card page-card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0">
                            <thead class="table-dark">
                            <tr>
                                <th>Nombre</th>
                                <th>Visibilidad</th>
                                <th>Inscripciones</th>
                                <th>Ubicación habitual</th>
                                <th class="text-end">Niveles</th>
                                <th class="text-end">Orden</th>
                                <th class="text-center" style="width: 220px;">Acciones</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($programs as $program)
                                <tr>
                                    <td>{{ $program->name }}</td>
                                    <td>
                                        <span class="badge {{ $program->is_public ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ $program->is_public ? 'Público' : 'Privado' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $program->acceptsPublicEnrollments() ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ $program->acceptsPublicEnrollments() ? 'Abiertas públicamente' : 'Cerradas públicamente' }}
                                        </span>
                                        @if ($program->enrollments_open && ! $program->is_public)
                                            <div class="small text-secondary mt-1">
                                                Apertura declarada; programa privado.
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($program->defaultLocation)
                                            {{ $program->defaultLocation->name }}
                                            <span class="badge {{ $program->defaultLocation->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                                {{ $program->defaultLocation->is_active ? 'Activa' : 'Inactiva' }}
                                            </span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="text-end">{{ $program->levels_count }}</td>
                                    <td class="text-end">{{ $program->sort_order }}</td>
                                    <td class="text-center">
                                        <div class="d-flex flex-wrap justify-content-center gap-2">
                                            <a
                                                href="{{ route('admin.school.levels.index', ['program' => $program->id]) }}"
                                                class="btn btn-sm btn-outline-primary"
                                            >
                                                Niveles
                                            </a>
                                            <a
                                                href="{{ route('admin.school.programs.edit', $program) }}"
                                                class="btn btn-sm btn-outline-secondary"
                                            >
                                                Editar
                                            </a>

                                            @if ($program->levels_count > 0 || $program->enrollments_count > 0)
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-danger"
                                                    disabled
                                                    title="El programa tiene niveles o inscripciones asociadas"
                                                >
                                                    Eliminar
                                                </button>
                                            @else
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.school.programs.destroy', $program) }}"
                                                    onsubmit="return confirm('¿Eliminar este programa de Escuela?')"
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
