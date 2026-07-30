@extends('admin.layout')

@section('content')
    @php
        $isPlanned = $activity->status === \App\Enums\EducationalActivityStatus::PLANNED;
    @endphp

    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">{{ $activity->name }}</h1>
                <p class="text-secondary mb-0">
                    {{ $activity->center->name }} ·
                    {{ $activity->activity_date->format('d/m/Y') }}
                </p>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a
                    href="{{ route('admin.school.educational-activities.edit', $activity) }}"
                    class="btn btn-outline-secondary"
                >
                    Editar
                </a>
                <a
                    href="{{ route('admin.school.educational-activities.index') }}"
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

        @if ($errors->any())
            <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="card page-card mb-4">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Estado</dt>
                    <dd class="col-sm-9">{{ $activity->status->label() }}</dd>
                    <dt class="col-sm-3">Centro</dt>
                    <dd class="col-sm-9">
                        <a
                            href="{{ route('admin.school.educational-centers.show', $activity->center) }}"
                        >
                            {{ $activity->center->name }}
                        </a>
                        · {{ $activity->center->locality }}
                        @unless ($activity->center->is_active)
                            <span class="badge text-bg-secondary">Inactivo</span>
                        @endunless
                    </dd>
                    <dt class="col-sm-3">Fecha</dt>
                    <dd class="col-sm-9">{{ $activity->activity_date->format('d/m/Y') }}</dd>
                    <dt class="col-sm-3">Horario</dt>
                    <dd class="col-sm-9">
                        {{ $activity->starts_at
                            ? $activity->startsAtLabel().'–'.$activity->endsAtLabel()
                            : 'Sin horario informado' }}
                    </dd>
                    <dt class="col-sm-3">Ubicación</dt>
                    <dd class="col-sm-9">
                        {{ $activity->location
                            ? $activity->location->name.' · '.$activity->location->locality
                            : 'Sin ubicación asociada' }}
                        @if ($activity->location && ! $activity->location->is_active)
                            <span class="badge text-bg-secondary">Inactiva</span>
                        @endif
                    </dd>
                    <dt class="col-sm-3">Alumnado previsto</dt>
                    <dd class="col-sm-9">{{ $activity->expected_students ?: '-' }}</dd>
                    <dt class="col-sm-3">Notas administrativas</dt>
                    <dd class="col-sm-9">
                        {{ $activity->admin_notes ?: '-' }}
                        <div class="form-text">Información privada.</div>
                    </dd>
                </dl>
            </div>
        </div>

        @if ($isPlanned)
            <div class="card page-card">
                <div class="card-body">
                    <h2 class="h5">Acciones de estado</h2>
                    <p class="text-secondary">
                        Las transiciones son definitivas. Una actividad cancelada que
                        se reprograme debe registrarse como una actividad nueva.
                    </p>

                    <div class="d-flex flex-wrap gap-2">
                        <form
                            method="POST"
                            action="{{ route('admin.school.educational-activities.complete', $activity) }}"
                            onsubmit="return confirm('¿Marcar esta actividad como completada?')"
                        >
                            @csrf
                            <button type="submit" class="btn btn-success">
                                Completar
                            </button>
                        </form>

                        <form
                            method="POST"
                            action="{{ route('admin.school.educational-activities.cancel', $activity) }}"
                            onsubmit="return confirm('¿Cancelar definitivamente esta actividad?')"
                        >
                            @csrf
                            <button type="submit" class="btn btn-warning">
                                Cancelar actividad
                            </button>
                        </form>

                        <form
                            method="POST"
                            action="{{ route('admin.school.educational-activities.destroy', $activity) }}"
                            onsubmit="return confirm('¿Eliminar esta actividad planificada creada por error?')"
                        >
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger">
                                Eliminar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @else
            <div class="alert alert-secondary">
                El estado histórico es definitivo: no se puede reactivar, volver a
                planificar ni eliminar esta actividad.
            </div>
        @endif
    </div>
@endsection
