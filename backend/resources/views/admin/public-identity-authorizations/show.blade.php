@extends('admin.layout')

@section('content')
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
            <div>
                <h1>Revisión de identidad pública</h1>
                <p class="text-secondary mb-0">Solicitud {{ $authorization->id }} · {{ $authorization->state->label() }}</p>
            </div>
            <a class="btn btn-outline-secondary" href="{{ route('admin.public-identity-authorizations.index') }}">Volver</a>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card page-card mb-4">
                    <div class="card-header fw-bold">Alcance y evidencia</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5">Ámbito</dt><dd class="col-sm-7">Competición pública</dd>
                            <dt class="col-sm-5">Modo</dt><dd class="col-sm-7">{{ $authorization->mode->label() }}</dd>
                            <dt class="col-sm-5">Aviso</dt><dd class="col-sm-7">{{ $authorization->notice_id }} · {{ $authorization->notice_version }}</dd>
                            <dt class="col-sm-5">Representante</dt><dd class="col-sm-7">{{ $authorization->guardian_name }} ({{ $authorization->guardian_relationship }})</dd>
                            <dt class="col-sm-5">Correo privado</dt><dd class="col-sm-7">{{ $authorization->guardian_email }}</dd>
                            <dt class="col-sm-5">Confirmación</dt><dd class="col-sm-7">{{ $authorization->guardian_confirmed_at?->format('d/m/Y H:i') ?? 'Pendiente' }}</dd>
                            <dt class="col-sm-5">Token</dt><dd class="col-sm-7">{{ $authorization->confirmation_token_used_at ? 'Usado o invalidado' : ($authorization->confirmation_token_hash ? 'Vigente hasta '.$authorization->confirmation_token_expires_at?->format('d/m/Y H:i') : 'No disponible') }}</dd>
                            <dt class="col-sm-5">Conformidad 14–17</dt><dd class="col-sm-7">{{ $authorization->minor_assent_recorded_at?->format('d/m/Y H:i') ?? 'No registrada' }}</dd>
                            <dt class="col-sm-5">Revisión</dt><dd class="col-sm-7">{{ $authorization->reviewed_at?->format('d/m/Y H:i') ?? 'Pendiente' }}</dd>
                        </dl>
                    </div>
                </div>

                <div class="card page-card mb-4">
                    <div class="card-header fw-bold">Sujeto</div>
                    <div class="card-body">
                        <p><strong>Inscripción:</strong> {{ $authorization->schoolEnrollment?->participant_name ?? 'No asociada' }}</p>
                        <p><strong>Nacimiento declarado:</strong> {{ $authorization->schoolEnrollment?->participant_birth_date?->format('d/m/Y') ?? '—' }}</p>
                        <p><strong>Jugador vinculado:</strong> {{ $authorization->player ? $authorization->player->user->name.' '.$authorization->player->user->lastname : 'Sin vincular' }}</p>
                        @if ($authorization->state === \App\Enums\PublicIdentityAuthorizationState::PENDING && (!$authorization->player_id || !$authorization->guardian_confirmed_at))
                            <p class="text-secondary">
                                La fecha de nacimiento sólo determina compatibilidad. Debes comprobar expresamente que la inscripción y el jugador corresponden a la misma persona.
                            </p>
                            @if ($players->isEmpty())
                                <div class="alert alert-warning mb-0">No hay jugadores compatibles con la fecha declarada. La autorización debe permanecer pendiente.</div>
                            @else
                            <form method="POST" action="{{ route('admin.public-identity-authorizations.link-player', $authorization) }}">
                                @csrf
                                <label for="player_id" class="form-label">Jugador compatible</label>
                                <select id="player_id" name="player_id" class="form-select mb-2" required>
                                    <option value="">Selecciona un jugador</option>
                                    @foreach ($players as $player)
                                        <option value="{{ $player->id }}" @selected((string) old('player_id') === (string) $player->id)>
                                            {{ $player->user->name }} {{ $player->user->lastname }}{{ $player->nickname ? ' · '.$player->nickname : '' }} — {{ $player->birth_date->format('d/m/Y') }}
                                        </option>
                                    @endforeach
                                </select>
                                <input type="hidden" name="link_confirmed" value="0">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" id="link_confirmed" name="link_confirmed" type="checkbox" value="1" required @checked(old('link_confirmed'))>
                                    <label class="form-check-label" for="link_confirmed">
                                        Confirmo que he comprobado que la inscripción y el jugador seleccionado corresponden a la misma persona.
                                    </label>
                                </div>
                                <button class="btn btn-outline-primary" type="submit">Vincular de forma explícita</button>
                            </form>
                            @endif
                        @elseif ($authorization->state === \App\Enums\PublicIdentityAuthorizationState::PENDING && $authorization->player_id && $authorization->guardian_confirmed_at)
                            <div class="alert alert-secondary mb-0">
                                El sujeto ya tiene evidencia confirmada. Para corregir el jugador, cierra o revoca esta solicitud y registra una autorización nueva.
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card page-card">
                    <div class="card-header fw-bold">Historial inmutable</div>
                    <div class="card-body">
                        <ol class="mb-0">
                            @foreach ($authorization->events as $event)
                                <li>
                                    {{ $event->occurred_at->format('d/m/Y H:i') }} — {{ $event->type->label() }}
                                    @if ($event->actor) por {{ $event->actor->name }} @endif
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="alert alert-light border">
                    <strong>{{ $notice['title'] }}</strong><br>
                    Versión {{ $notice['version'] }} · {{ $notice['summary'] }}
                </div>

                @if ($authorization->state === \App\Enums\PublicIdentityAuthorizationState::PENDING)
                    @if (!$authorization->guardian_confirmed_at && $authorization->mode !== \App\Enums\PublicIdentityAuthorizationMode::ANONYMOUS)
                        <form method="POST" action="{{ route('admin.public-identity-authorizations.resend', $authorization) }}" class="mb-3">
                            @csrf
                            <button class="btn btn-outline-primary w-100" type="submit">Reenviar confirmación</button>
                        </form>
                    @endif

                    @if ($authorization->player && app(\App\Services\PublicIdentityAuthorizationService::class)->requiresMinorAssent($authorization->player) && !$authorization->minor_assent_recorded_at)
                        <form method="POST" action="{{ route('admin.public-identity-authorizations.record-assent', $authorization) }}" class="card page-card mb-3">
                            @csrf
                            <div class="card-body">
                                <input type="hidden" name="assent_confirmed" value="0">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" id="assent_confirmed" name="assent_confirmed" type="checkbox" value="1" required>
                                    <label class="form-check-label" for="assent_confirmed">
                                        Confirmo que el menor ha sido informado conforme al aviso {{ $authorization->notice_version }} y ha manifestado su conformidad.
                                    </label>
                                </div>
                                <button class="btn btn-outline-primary w-100" type="submit">Registrar conformidad</button>
                            </div>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('admin.public-identity-authorizations.approve', $authorization) }}" class="card page-card mb-3">
                        @csrf
                        <div class="card-body">
                            <label for="approve_reason" class="form-label">Nota privada opcional</label>
                            <textarea id="approve_reason" name="private_reason" class="form-control mb-2" maxlength="1000"></textarea>
                            <button class="btn btn-success w-100" type="submit">Aprobar tras revisión</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('admin.public-identity-authorizations.deny', $authorization) }}" class="card page-card">
                        @csrf
                        <div class="card-body">
                            <label for="deny_reason" class="form-label">Motivo privado opcional</label>
                            <textarea id="deny_reason" name="private_reason" class="form-control mb-2" maxlength="1000"></textarea>
                            <button class="btn btn-outline-danger w-100" type="submit">Denegar</button>
                        </div>
                    </form>
                @elseif ($authorization->state === \App\Enums\PublicIdentityAuthorizationState::APPROVED)
                    <form method="POST" action="{{ route('admin.public-identity-authorizations.revoke', $authorization) }}" class="card page-card">
                        @csrf
                        <div class="card-body">
                            <label for="revoke_reason" class="form-label">Motivo privado opcional</label>
                            <textarea id="revoke_reason" name="private_reason" class="form-control mb-2" maxlength="1000"></textarea>
                            <button class="btn btn-outline-danger w-100" type="submit">Revocar inmediatamente</button>
                        </div>
                    </form>
                @else
                    <div class="alert alert-secondary">El estado es histórico. Para otra autorización debe registrarse una solicitud nueva.</div>
                @endif
            </div>
        </div>
    </div>
@endsection
