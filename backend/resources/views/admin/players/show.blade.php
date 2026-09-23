@extends('admin.layout')

@section('content')

    <h1>Detalle de jugador</h1>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <p><strong>ID:</strong> {{ $player->id }}</p>
    <p><strong>Apodo:</strong> {{ $player->nickname ?: '—' }}</p>
    <p><strong>Slug:</strong> {{ $player->slug }}</p>
    <p><strong>DNI:</strong> {{ $player->dni ?: '—' }}</p>
    <p><strong>Fecha de nacimiento:</strong> {{ $player->birth_date ? $player->birth_date->format('d/m/Y') : '—' }}</p>
    <p><strong>Género:</strong> {{ $player->gender?->label() ?? '—' }}</p>
    <p><strong>Nivel:</strong> {{ $player->level }}</p>
    <p><strong>Número de licencia:</strong> {{ $player->license_number ?: '—' }}</p>
    <p><strong>Mano dominante:</strong>
        @switch($player->dominant_hand)
            @case('right')
                Derecha
                @break
            @case('left')
                Izquierda
                @break
            @case('both')
                Ambas
                @break
            @default
                —
        @endswitch
    </p>
    <p><strong>Notas:</strong> {{ $player->notes ?: '—' }}</p>
    <p><strong>Activo:</strong> {{ $player->active ? 'Sí' : 'No' }}</p>

    <hr>

    <h2>Usuario asociado</h2>
    <p><strong>Nombre:</strong> {{ trim(($player->user?->name ?? '') . ' ' . ($player->user?->lastname ?? '')) ?: '—' }}</p>
    <p><strong>Email:</strong> {{ $player->user?->email ?? '—' }}</p>

    @if($player->user)
        <p>
            <a href="{{ route('admin.users.show', $player->user) }}">
                Ver usuario asociado
            </a>
        </p>
    @endif

    @if ($isMinor)
        <div class="card page-card my-4">
            <div class="card-header fw-bold">Identidad pública del menor</div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-4">Proyección pública actual</dt>
                    <dd class="col-sm-8">{{ $publicIdentityDisplayName }}</dd>
                    <dt class="col-sm-4">Autorización relevante</dt>
                    <dd class="col-sm-8">
                        @if ($displayAuthorization)
                            {{ $displayAuthorization->state->label() }} · {{ $displayAuthorization->mode->label() }}
                            <a class="ms-2" href="{{ route('admin.public-identity-authorizations.show', $displayAuthorization) }}">
                                Ver detalle
                            </a>
                        @else
                            Ninguna
                        @endif
                    </dd>
                </dl>

                @if (! $authorizationEnabled)
                    <div class="alert alert-info mb-0">
                        Las solicitudes de identidad pública están desactivadas. La proyección permanece cerrada según la política vigente.
                    </div>
                @elseif ($blockingAuthorization)
                    <div class="alert alert-info mb-0">
                        Ya existe una autorización {{ strtolower($blockingAuthorization->state->label()) }} para este alcance. Debe completarse o cerrarse su ciclo antes de registrar otra.
                    </div>
                @elseif (! $notificationEnabled)
                    <div class="alert alert-info mb-0">
                        El envío de confirmaciones está desactivado. No puede iniciarse una solicitud directa sin el correo de confirmación del representante.
                    </div>
                @elseif ($availableAuthorizationModes->isEmpty())
                    <div class="alert alert-info mb-0">
                        No hay un modo afirmativo disponible: el jugador necesita un alias deportivo publicable o nombres de pila y primer apellido suficientes.
                    </div>
                @else
                    <form method="POST" action="{{ route('admin.players.public-identity-authorizations.store', $player) }}" class="row g-3">
                        @csrf
                        <div class="col-md-6">
                            <label for="guardian_name" class="form-label">Representante</label>
                            <input
                                id="guardian_name"
                                name="guardian_name"
                                type="text"
                                maxlength="255"
                                value="{{ old('guardian_name') }}"
                                class="form-control @error('guardian_name') is-invalid @enderror"
                                required
                            >
                            @error('guardian_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="guardian_relationship" class="form-label">Relación con el menor</label>
                            <input
                                id="guardian_relationship"
                                name="guardian_relationship"
                                type="text"
                                maxlength="100"
                                value="{{ old('guardian_relationship') }}"
                                class="form-control @error('guardian_relationship') is-invalid @enderror"
                                required
                            >
                            @error('guardian_relationship')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="guardian_email" class="form-label">Correo del representante</label>
                            <input
                                id="guardian_email"
                                name="guardian_email"
                                type="email"
                                maxlength="254"
                                value="{{ old('guardian_email') }}"
                                class="form-control @error('guardian_email') is-invalid @enderror"
                                required
                            >
                            @error('guardian_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="mode" class="form-label">Modo solicitado</label>
                            <select id="mode" name="mode" class="form-select @error('mode') is-invalid @enderror" required>
                                <option value="">Selecciona un modo</option>
                                @foreach ($availableAuthorizationModes as $mode)
                                    <option value="{{ $mode->value }}" @selected(old('mode') === $mode->value)>
                                        {{ $mode->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @if (! $availableAuthorizationModes->contains(\App\Enums\PublicIdentityAuthorizationMode::ALIAS))
                                <div class="form-text">Alias no disponible: el jugador no tiene un alias deportivo publicable.</div>
                            @endif
                            @if (! $availableAuthorizationModes->contains(\App\Enums\PublicIdentityAuthorizationMode::NAME_INITIAL))
                                <div class="form-text">Nombre e inicial no disponibles: faltan nombres de pila o primer apellido.</div>
                            @endif
                        </div>

                        <div class="col-12">
                            <input type="hidden" name="guardian_authority_declared" value="0">
                            <div class="form-check">
                                <input
                                    id="guardian_authority_declared"
                                    name="guardian_authority_declared"
                                    type="checkbox"
                                    value="1"
                                    class="form-check-input @error('guardian_authority_declared') is-invalid @enderror"
                                    @checked(old('guardian_authority_declared'))
                                    required
                                >
                                <label class="form-check-label" for="guardian_authority_declared">
                                    Confirmo que el representante indicado ha declarado ante el Club que ejerce la patria potestad o tutela sobre el menor y solicita tramitar esta autorización.
                                </label>
                                @error('guardian_authority_declared')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <input type="hidden" name="notice_id" value="{{ $publicIdentityNotice['id'] }}">
                        <input type="hidden" name="notice_version" value="{{ $publicIdentityNotice['version'] }}">
                        <div class="col-12">
                            <div class="alert alert-light border mb-0">
                                <strong>{{ $publicIdentityNotice['title'] }}</strong><br>
                                {{ $publicIdentityNotice['id'] }} · versión {{ $publicIdentityNotice['version'] }}<br>
                                <span class="text-secondary">{{ $publicIdentityNotice['summary'] }}</span>
                            </div>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary" type="submit">Solicitar autorización de identidad pública</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif

    <br>

    <a href="{{ route('admin.players.index') }}">Volver</a>
    |
    <a href="{{ route('admin.players.edit', $player) }}">Editar</a>

@endsection
