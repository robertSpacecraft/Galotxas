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

        <div class="row g-4">
            <div class="col-lg-8">
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

                <div class="card page-card">
                    <div class="card-header fw-bold">Mensaje original</div>
                    <div class="card-body">
                        <p class="mb-0">{!! nl2br(e($contactRequest->message)) !!}</p>
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

                            <dt>Consentimiento registrado</dt>
                            <dd>
                                <time datetime="{{ $contactRequest->consent_at->toIso8601String() }}">
                                    {{ $contactRequest->consent_at->format('d/m/Y H:i') }}
                                </time>
                            </dd>

                            <dt>Última actualización</dt>
                            <dd class="mb-0">
                                {{ $contactRequest->updated_at->format('d/m/Y H:i') }}
                            </dd>
                        </dl>
                    </div>
                </div>

                @if ($contactRequest->status === \App\Enums\ContactRequestStatus::NEW)
                    <div class="card page-card mb-4">
                        <div class="card-header fw-bold">Revisión</div>
                        <div class="card-body">
                            <form
                                method="POST"
                                action="{{ route('admin.contact-requests.mark-as-read', $contactRequest) }}"
                            >
                                @csrf
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    Marcar como leída
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

                @if ($contactRequest->status !== \App\Enums\ContactRequestStatus::CLOSED)
                    <div class="card page-card">
                        <div class="card-header fw-bold">Cerrar solicitud</div>
                        <div class="card-body">
                            <p class="text-secondary">
                                El cierre conserva íntegramente el mensaje original.
                            </p>
                            <form
                                method="POST"
                                action="{{ route('admin.contact-requests.close', $contactRequest) }}"
                                onsubmit="return confirm('¿Cerrar esta solicitud de contacto?')"
                            >
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary w-100">
                                    Cerrar
                                </button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
