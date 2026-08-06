@extends('admin.layout')

@section('content')
    <div class="container mt-4">
        <h1>Identidad pública de menores</h1>
        <p class="text-secondary">
            Autorizaciones específicas para competición. La inscripción y la identidad pública son independientes.
        </p>

        <div class="card page-card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="state" class="form-label">Estado</label>
                        <select id="state" name="state" class="form-select">
                            <option value="">Todos</option>
                            @foreach ($states as $state)
                                <option value="{{ $state->value }}" @selected(($filters['state'] ?? '') === $state->value)>
                                    {{ $state->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="mode" class="form-label">Modo</label>
                        <select id="mode" name="mode" class="form-select">
                            <option value="">Todos</option>
                            @foreach ($modes as $mode)
                                <option value="{{ $mode->value }}" @selected(($filters['mode'] ?? '') === $mode->value)>
                                    {{ $mode->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="age_group" class="form-label">Grupo de edad</label>
                        <select id="age_group" name="age_group" class="form-select">
                            <option value="">Todos</option>
                            <option value="under_14" @selected(($filters['age_group'] ?? '') === 'under_14')>Menor de 14</option>
                            <option value="14_to_17" @selected(($filters['age_group'] ?? '') === '14_to_17')>De 14 a 17</option>
                            <option value="adult" @selected(($filters['age_group'] ?? '') === 'adult')>Ya adulta</option>
                            <option value="unlinked" @selected(($filters['age_group'] ?? '') === 'unlinked')>Sin jugador vinculado</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button class="btn btn-outline-primary" type="submit">Filtrar</button>
                        <a class="btn btn-outline-secondary" href="{{ route('admin.public-identity-authorizations.index') }}">Limpiar</a>
                    </div>
                    <div class="col-md-3">
                        <label for="from" class="form-label">Desde</label>
                        <input id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label for="to" class="form-label">Hasta</label>
                        <input id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}" class="form-control">
                    </div>
                </form>
            </div>
        </div>

        <div class="card page-card">
            <div class="card-body">
                @if ($authorizations->isEmpty())
                    <p class="text-secondary mb-0">No hay autorizaciones para este filtro.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle">
                            <thead class="table-dark">
                            <tr>
                                <th>Solicitud</th>
                                <th>Inscripción</th>
                                <th>Jugador</th>
                                <th>Modo</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($authorizations as $authorization)
                                <tr>
                                    <td>{{ $authorization->requested_at->format('d/m/Y H:i') }}</td>
                                    <td>{{ $authorization->schoolEnrollment?->participant_name ?? 'Sin inscripción' }}</td>
                                    <td>{{ $authorization->player?->user?->name ?? 'Sin vincular' }}</td>
                                    <td>{{ $authorization->mode->label() }}</td>
                                    <td>{{ $authorization->state->label() }}</td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.public-identity-authorizations.show', $authorization) }}">
                                            Revisar
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    {{ $authorizations->links() }}
                @endif
            </div>
        </div>
    </div>
@endsection
