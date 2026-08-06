@extends('admin.layout')

@section('content')
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Detalle de inscripción</h1>
                <p class="text-secondary mb-0">
                    Solicitud recibida el {{ $enrollment->requested_at->format('d/m/Y \a \l\a\s H:i') }}
                </p>
            </div>

            <div class="d-flex gap-2">
                <a
                    href="{{ route('admin.school.enrollments.edit', $enrollment) }}"
                    class="btn btn-outline-secondary"
                >
                    Corregir datos
                </a>
                <a href="{{ route('admin.school.enrollments.index') }}" class="btn btn-outline-secondary">
                    Volver al listado
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
                <strong>No se ha podido completar la acción:</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card page-card mb-4">
                    <div class="card-header fw-bold">Participante y contacto</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Participante</dt>
                            <dd class="col-sm-8">{{ $enrollment->participant_name }}</dd>

                            <dt class="col-sm-4">Nacimiento</dt>
                            <dd class="col-sm-8">{{ $enrollment->participant_birth_date->format('d/m/Y') }}</dd>

                            <dt class="col-sm-4">Condición al solicitar</dt>
                            <dd class="col-sm-8">
                                {{ $enrollment->wasMinorAtRequest() ? 'Menor de edad' : 'Persona adulta' }}
                            </dd>

                            <dt class="col-sm-4">Teléfono</dt>
                            <dd class="col-sm-8">{{ $enrollment->contact_phone }}</dd>

                            <dt class="col-sm-4">Correo</dt>
                            <dd class="col-sm-8">{{ $enrollment->contact_email }}</dd>

                            <dt class="col-sm-4">Cuenta vinculada</dt>
                            <dd class="col-sm-8">{{ $enrollment->user ? 'Sí' : 'No' }}</dd>
                        </dl>
                    </div>
                </div>

                <div class="card page-card mb-4">
                    <div class="card-header fw-bold">Representante</div>
                    <div class="card-body">
                        @if ($enrollment->guardian_name)
                            <dl class="row mb-0">
                                <dt class="col-sm-4">Nombre</dt>
                                <dd class="col-sm-8">{{ $enrollment->guardian_name }}</dd>

                                <dt class="col-sm-4">Relación</dt>
                                <dd class="col-sm-8">{{ $enrollment->guardian_relationship }}</dd>
                            </dl>
                        @else
                            <p class="text-secondary mb-0">No procede para este participante adulto.</p>
                        @endif
                    </div>
                </div>

                <div class="card page-card">
                    <div class="card-header fw-bold">Notas administrativas</div>
                    <div class="card-body">
                        @if ($enrollment->admin_notes)
                            <p class="mb-0">{!! nl2br(e($enrollment->admin_notes)) !!}</p>
                        @else
                            <p class="text-secondary mb-0">Sin notas internas.</p>
                        @endif
                    </div>
                </div>

                <div class="card page-card mt-4">
                    <div class="card-header fw-bold">Identidad pública de competición</div>
                    <div class="card-body">
                        @forelse ($enrollment->publicIdentityAuthorizations as $authorization)
                            <p class="mb-2">
                                {{ $authorization->mode->label() }} —
                                {{ $authorization->state->label() }} —
                                aviso {{ $authorization->notice_version }}
                                <a
                                    href="{{ route('admin.public-identity-authorizations.show', $authorization) }}"
                                    class="ms-2"
                                >
                                    Revisar autorización
                                </a>
                            </p>
                        @empty
                            <p class="text-secondary mb-0">
                                La inscripción no incluye una decisión de identidad pública.
                            </p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card page-card mb-4">
                    <div class="card-header fw-bold">Estado y asignación</div>
                    <div class="card-body">
                        <dl>
                            <dt>Estado</dt>
                            <dd>
                                <span class="badge text-bg-secondary">
                                    {{ $enrollment->status->label() }}
                                </span>
                            </dd>

                            <dt>Programa</dt>
                            <dd>{{ $enrollment->program->name }}</dd>

                            <dt>Nivel</dt>
                            <dd>{{ $enrollment->level?->name ?? 'Sin asignar' }}</dd>

                            <dt>Solicitada</dt>
                            <dd>{{ $enrollment->requested_at->format('d/m/Y H:i') }}</dd>

                            <dt>Activada</dt>
                            <dd>{{ $enrollment->activated_at?->format('d/m/Y H:i') ?? '—' }}</dd>

                            <dt>Rechazada</dt>
                            <dd>{{ $enrollment->rejected_at?->format('d/m/Y H:i') ?? '—' }}</dd>

                            <dt>Baja</dt>
                            <dd class="mb-0">{{ $enrollment->withdrawn_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                        </dl>
                    </div>
                </div>

                @if ($enrollment->status === \App\Enums\SchoolEnrollmentStatus::PENDING)
                    <div class="card page-card mb-4">
                        <div class="card-header fw-bold">Resolver solicitud pendiente</div>
                        <div class="card-body">
                            <form
                                method="POST"
                                action="{{ route('admin.school.enrollments.approve', $enrollment) }}"
                                class="mb-3"
                            >
                                @csrf
                                <label for="school_level_id" class="form-label">
                                    Nivel obligatorio al aprobar
                                </label>
                                <select
                                    id="school_level_id"
                                    name="school_level_id"
                                    required
                                    class="form-select @error('school_level_id') is-invalid @enderror mb-2"
                                >
                                    <option value="">Selecciona un nivel activo</option>
                                    @foreach ($availableLevels as $level)
                                        <option
                                            value="{{ $level->id }}"
                                            @selected((string) old('school_level_id', $enrollment->school_level_id) === (string) $level->id)
                                        >
                                            {{ $level->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('school_level_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <button type="submit" class="btn btn-success w-100">
                                    Aprobar y activar
                                </button>
                            </form>

                            <form
                                method="POST"
                                action="{{ route('admin.school.enrollments.reject', $enrollment) }}"
                                onsubmit="return confirm('¿Rechazar esta solicitud? Se conservará el histórico.')"
                            >
                                @csrf
                                <button type="submit" class="btn btn-outline-danger w-100">
                                    Rechazar solicitud
                                </button>
                            </form>
                        </div>
                    </div>
                @elseif ($enrollment->status === \App\Enums\SchoolEnrollmentStatus::ACTIVE)
                    <div class="card page-card mb-4">
                        <div class="card-header fw-bold">Gestión de participante activo</div>
                        <div class="card-body">
                            <form
                                method="POST"
                                action="{{ route('admin.school.enrollments.reassign-level', $enrollment) }}"
                                class="mb-3"
                            >
                                @csrf
                                <label for="school_level_id" class="form-label">Cambiar nivel</label>
                                <select
                                    id="school_level_id"
                                    name="school_level_id"
                                    required
                                    class="form-select @error('school_level_id') is-invalid @enderror mb-2"
                                >
                                    @foreach ($availableLevels as $level)
                                        <option
                                            value="{{ $level->id }}"
                                            @selected((string) old('school_level_id', $enrollment->school_level_id) === (string) $level->id)
                                        >
                                            {{ $level->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('school_level_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    Reasignar nivel
                                </button>
                            </form>

                            <form
                                method="POST"
                                action="{{ route('admin.school.enrollments.withdraw', $enrollment) }}"
                                onsubmit="return confirm('¿Dar de baja al participante? Se conservará el histórico.')"
                            >
                                @csrf
                                <button type="submit" class="btn btn-outline-danger w-100">
                                    Dar de baja
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="alert alert-light border">
                        Esta inscripción se conserva para consulta histórica. No puede reactivarse.
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
