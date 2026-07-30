@extends('admin.layout')

@section('content')
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">{{ $center->name }}</h1>
                <p class="text-secondary mb-0">{{ $center->locality }}</p>
            </div>

            <div class="d-flex flex-wrap gap-2">
                @if ($center->is_active)
                    <a
                        href="{{ route('admin.school.educational-activities.create', ['center' => $center->id]) }}"
                        class="btn btn-primary"
                    >
                        Crear actividad
                    </a>
                @endif
                <a
                    href="{{ route('admin.school.educational-centers.edit', $center) }}"
                    class="btn btn-outline-secondary"
                >
                    Editar
                </a>
                <a
                    href="{{ route('admin.school.educational-centers.index') }}"
                    class="btn btn-outline-secondary"
                >
                    Volver
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card page-card mb-4">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Estado</dt>
                    <dd class="col-sm-9">
                        <span class="badge {{ $center->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $center->is_active ? 'Activo' : 'Inactivo' }}
                        </span>
                    </dd>
                    <dt class="col-sm-3">Persona de contacto</dt>
                    <dd class="col-sm-9">{{ $center->contact_name ?: '-' }}</dd>
                    <dt class="col-sm-3">Teléfono</dt>
                    <dd class="col-sm-9">{{ $center->contact_phone ?: '-' }}</dd>
                    <dt class="col-sm-3">Correo</dt>
                    <dd class="col-sm-9">{{ $center->contact_email ?: '-' }}</dd>
                    <dt class="col-sm-3">Notas administrativas</dt>
                    <dd class="col-sm-9">
                        {{ $center->admin_notes ?: '-' }}
                        <div class="form-text">Información privada.</div>
                    </dd>
                </dl>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h4 mb-0">Histórico de actividades</h2>
            <span class="text-secondary">{{ $activities->count() }} actividades</span>
        </div>

        @if ($activities->isEmpty())
            <div class="alert alert-info">Este centro todavía no tiene actividades.</div>
        @else
            <div class="card page-card mb-4">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="table-dark">
                            <tr>
                                <th>Fecha</th>
                                <th>Actividad</th>
                                <th>Horario</th>
                                <th>Ubicación</th>
                                <th>Alumnado previsto</th>
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
                                        {{ $activity->starts_at
                                            ? $activity->startsAtLabel().'–'.$activity->endsAtLabel()
                                            : '-' }}
                                    </td>
                                    <td>{{ $activity->location?->name ?: '-' }}</td>
                                    <td>{{ $activity->expected_students ?: '-' }}</td>
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
        @endif

        @if ($activities->isEmpty())
            <form
                method="POST"
                action="{{ route('admin.school.educational-centers.destroy', $center) }}"
                onsubmit="return confirm('¿Eliminar este centro educativo?')"
            >
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger">
                    Eliminar centro
                </button>
            </form>
        @else
            <button
                type="button"
                class="btn btn-outline-danger"
                disabled
                title="El centro conserva actividades"
            >
                Eliminar centro
            </button>
        @endif
    </div>
@endsection
