@extends('admin.layout')

@section('content')
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Detalle de solicitud de contacto</h1>
                <p class="text-secondary mb-0">
                    Recibida el {{ $contactRequest->created_at->format('d/m/Y \a \l\a\s H:i') }}
                </p>
            </div>

            <a href="{{ route('admin.contact-requests.index') }}" class="btn btn-outline-secondary">
                Volver al listado
            </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row g-4">
            <div class="col-lg-8">
                @if ($contactRequest->isAnonymized())
                    <div class="alert alert-secondary">
                        Los datos personales y el contenido original fueron anonimizados el
                        {{ $contactRequest->anonymized_at->format('d/m/Y H:i') }}.
                    </div>
                @else
                    <div class="card page-card mb-4">
                        <div class="card-header fw-bold">Remitente y asunto</div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-3">Nombre</dt>
                                <dd class="col-sm-9">{{ $contactRequest->name }}</dd>

                                <dt class="col-sm-3">Correo</dt>
                                <dd class="col-sm-9">{{ $contactRequest->email }}</dd>

                                <dt class="col-sm-3">Asunto</dt>
                                <dd class="col-sm-9 mb-0">{{ $contactRequest->subject }}</dd>
                            </dl>
                        </div>
                    </div>

                    <div class="card page-card mb-4">
                        <div class="card-header fw-bold">Mensaje original</div>
                        <div class="card-body">
                            <p class="mb-0">{!! nl2br(e($contactRequest->message)) !!}</p>
                        </div>
                    </div>
                @endif

                <div class="card page-card">
                    <div class="card-header fw-bold">Historial mínimo</div>
                    <div class="card-body">
                        @if ($contactRequest->events->isEmpty())
                            <p class="text-secondary mb-0">No consta historial para este registro legado.</p>
                        @else
                            <ul class="list-group list-group-flush">
                                @foreach ($contactRequest->events as $event)
                                    <li class="list-group-item px-0">
                                        <strong>{{ $event->type->label() }}</strong>
                                        <span class="text-secondary">
                                            — {{ $event->occurred_at->format('d/m/Y H:i') }}
                                            @if ($event->actor)
                                                — {{ $event->actor->name }} {{ $event->actor->lastname }}
                                            @endif
                                        </span>
                                        @if (isset($event->metadata['attempt']))
                                            <span class="d-block text-secondary">
                                                Intento {{ $event->metadata['attempt'] }}
                                                @if (isset($event->metadata['failure_code']))
                                                    · Código {{ $event->metadata['failure_code'] }}
                                                @endif
                                            </span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card page-card mb-4">
                    <div class="card-header fw-bold">Estado y privacidad</div>
                    <div class="card-body">
                        <dl class="mb-0">
                            <dt>Estado</dt>
                            <dd>
                                <span class="badge {{ $contactRequest->status->badgeClass() }}">
                                    {{ $contactRequest->status->label() }}
                                </span>
                            </dd>

                            <dt>Notificación</dt>
                            <dd>
                                <span class="badge {{ $contactRequest->notification_status->badgeClass() }}">
                                    {{ $contactRequest->notification_status->label() }}
                                </span>
                                <span class="d-block text-secondary">
                                    {{ $contactRequest->notification_attempt_count }} intento(s)
                                </span>
                            </dd>

                            <dt>Aviso aceptado</dt>
                            @if ($contactRequest->isLegacy())
                                <dd><span class="badge bg-secondary">Legado sin versión acreditada</span></dd>
                            @else
                                <dd>
                                    {{ $contactRequest->privacy_notice_id }}<br>
                                    Versión {{ $contactRequest->privacy_notice_version }}
                                </dd>
                            @endif

                            <dt>Consentimiento registrado</dt>
                            <dd>
                                @if ($contactRequest->consent_at)
                                    <time datetime="{{ $contactRequest->consent_at->toIso8601String() }}">
                                        {{ $contactRequest->consent_at->format('d/m/Y H:i') }}
                                    </time>
                                @else
                                    No acreditado
                                @endif
                            </dd>

                            <dt>Cierre</dt>
                            <dd>{{ $contactRequest->closed_at?->format('d/m/Y H:i') ?? 'Pendiente' }}</dd>

                            <dt>Fin de retención</dt>
                            <dd>{{ $contactRequest->retention_until?->format('d/m/Y H:i') ?? 'No iniciado' }}</dd>

                            <dt>Suspensión de eliminación</dt>
                            <dd>
                                {{ $contactRequest->retention_hold ? 'Activa' : 'No activa' }}
                                @if ($contactRequest->retention_hold_reason)
                                    <span class="d-block text-secondary">
                                        Motivo: {{ $contactRequest->retention_hold_reason }}
                                    </span>
                                @endif
                            </dd>

                            @if ($contactRequest->notification_failure_code)
                                <dt>Último fallo técnico</dt>
                                <dd>{{ $contactRequest->notification_failure_code }}</dd>
                            @endif

                            <dt>Última actualización</dt>
                            <dd class="mb-0">{{ $contactRequest->updated_at->format('d/m/Y H:i') }}</dd>
                        </dl>
                    </div>
                </div>

                @if ($contactRequest->status === \App\Enums\ContactRequestStatus::NEW)
                    <div class="card page-card mb-4">
                        <div class="card-header fw-bold">Revisión</div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('admin.contact-requests.mark-as-read', $contactRequest) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    Marcar como leída
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

                @if ($contactRequest->status !== \App\Enums\ContactRequestStatus::CLOSED)
                    <div class="card page-card mb-4">
                        <div class="card-header fw-bold">Cerrar solicitud</div>
                        <div class="card-body">
                            <p class="text-secondary">El cierre inicia el plazo de retención de 12 meses.</p>
                            <form
                                method="POST"
                                action="{{ route('admin.contact-requests.close', $contactRequest) }}"
                                onsubmit="return confirm('¿Cerrar esta solicitud de contacto?')"
                            >
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary w-100">Cerrar</button>
                            </form>
                        </div>
                    </div>
                @endif

                @if (
                    ! $contactRequest->isAnonymized()
                    && in_array($contactRequest->notification_status, [
                        \App\Enums\ContactNotificationStatus::FAILED,
                        \App\Enums\ContactNotificationStatus::DISABLED,
                    ], true)
                    && $contactRequest->notification_attempt_count
                        < (int) config('contact.notification.max_attempts', 3)
                )
                    <div class="card page-card mb-4">
                        <div class="card-header fw-bold">Notificación auxiliar</div>
                        <div class="card-body">
                            <p class="text-secondary">Reintenta sólo tras revisar la configuración de correo.</p>
                            <form method="POST" action="{{ route('admin.contact-requests.retry-notification', $contactRequest) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary w-100">Reintentar notificación</button>
                            </form>
                        </div>
                    </div>
                @endif

                @if ($contactRequest->status === \App\Enums\ContactRequestStatus::CLOSED && ! $contactRequest->isAnonymized())
                    <div class="card page-card mb-4">
                        <div class="card-header fw-bold">Conservación</div>
                        <div class="card-body">
                            @if ($contactRequest->retention_hold)
                                <form method="POST" action="{{ route('admin.contact-requests.release-retention-hold', $contactRequest) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-secondary w-100">Liberar suspensión</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.contact-requests.retention-hold', $contactRequest) }}">
                                    @csrf
                                    <label for="retention_hold_reason" class="form-label">Motivo mínimo</label>
                                    <textarea
                                        id="retention_hold_reason"
                                        name="retention_hold_reason"
                                        class="form-control mb-3"
                                        rows="3"
                                        maxlength="500"
                                        required
                                    >{{ old('retention_hold_reason') }}</textarea>
                                    <button type="submit" class="btn btn-outline-warning w-100">Suspender eliminación</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endif

                @if (
                    $contactRequest->status === \App\Enums\ContactRequestStatus::CLOSED
                    && $contactRequest->retention_until?->isPast()
                    && ! $contactRequest->retention_hold
                    && ! $contactRequest->isAnonymized()
                )
                    <div class="card page-card border-danger">
                        <div class="card-header fw-bold text-danger">Anonimizar datos vencidos</div>
                        <div class="card-body">
                            <form
                                method="POST"
                                action="{{ route('admin.contact-requests.anonymize', $contactRequest) }}"
                                onsubmit="return confirm('Esta acción elimina los datos personales y no puede deshacerse. ¿Continuar?')"
                            >
                                @csrf
                                <div class="form-check mb-3">
                                    <input
                                        id="confirm_anonymization"
                                        name="confirm_anonymization"
                                        value="1"
                                        type="checkbox"
                                        class="form-check-input"
                                        required
                                    >
                                    <label for="confirm_anonymization" class="form-check-label">
                                        Confirmo que el plazo venció y no existe una suspensión.
                                    </label>
                                </div>
                                <button type="submit" class="btn btn-outline-danger w-100">Anonimizar</button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
