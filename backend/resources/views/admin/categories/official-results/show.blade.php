@extends('admin.layout')

@section('content')
    @php
        $isLeague = $officialResult->competition_part === \App\Enums\OfficialResultCompetitionPart::LEAGUE;
        $isOfficial = $officialResult->status === \App\Enums\OfficialResultStatus::OFFICIAL;
        $partLabel = $isLeague ? 'Liga' : 'Copa';
    @endphp

    <div class="row g-4">
        <div class="col-12">
            <div class="card page-card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div>
                            <h1 class="h3 mb-2">Resultado oficial de {{ $partLabel }} · v{{ $officialResult->version }}</h1>
                            <p class="text-secondary mb-0">
                                {{ $category->championship->season->name ?? 'Temporada sin nombre' }} ·
                                {{ $category->championship->name }} · {{ $category->name }}
                            </p>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            @if ($isOfficial)
                                <a
                                    href="{{ route('admin.categories.official-results.reopen.form', [$category, $officialResult]) }}"
                                    class="btn btn-outline-danger"
                                >
                                    Reabrir
                                </a>
                            @endif
                            <a href="{{ route('admin.categories.show', $category) }}" class="btn btn-outline-secondary">
                                Volver a categoría
                            </a>
                        </div>
                    </div>

                    <hr>

                    <dl class="row mb-0">
                        <dt class="col-md-3">Parte</dt>
                        <dd class="col-md-9">{{ $partLabel }}</dd>
                        <dt class="col-md-3">Versión</dt>
                        <dd class="col-md-9">v{{ $officialResult->version }}</dd>
                        <dt class="col-md-3">Estado</dt>
                        <dd class="col-md-9">
                            <span class="badge {{ $isOfficial ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ $isOfficial ? 'Oficial' : 'Reabierto' }}
                            </span>
                        </dd>
                        <dt class="col-md-3">Oficializado</dt>
                        <dd class="col-md-9">{{ $officialResult->officialized_at?->format('d/m/Y H:i:s') ?? 'Sin fecha' }}</dd>
                        <dt class="col-md-3">Por</dt>
                        <dd class="col-md-9">{{ $officialResult->officialized_by_name_snapshot ?: 'Sin actor registrado' }}</dd>

                        @if ($officialResult->reopened_at)
                            <dt class="col-md-3">Reabierto</dt>
                            <dd class="col-md-9">{{ $officialResult->reopened_at->format('d/m/Y H:i:s') }}</dd>
                            <dt class="col-md-3">Reabierto por</dt>
                            <dd class="col-md-9">{{ $officialResult->reopened_by_name_snapshot ?: 'Sin actor registrado' }}</dd>
                            <dt class="col-md-3">Motivo</dt>
                            <dd class="col-md-9" style="white-space: pre-wrap;">{{ $officialResult->reopen_reason }}</dd>
                        @endif

                        <dt class="col-md-3">Digest de la fuente</dt>
                        <dd class="col-md-9"><code class="text-break">{{ $officialResult->source_digest }}</code></dd>
                    </dl>
                </div>
            </div>
        </div>

        @if ($isLeague)
            <div class="col-12">
                <div class="card page-card">
                    <div class="card-body">
                        <h2 class="h4 section-title">Clasificación persistida</h2>

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle mb-0">
                                <thead class="table-dark">
                                <tr>
                                    <th>Posición</th>
                                    <th>Source entry</th>
                                    <th>Identidad histórica</th>
                                    <th>Identidad pública</th>
                                    <th>Proyección</th>
                                    <th>PJ</th>
                                    <th>PG</th>
                                    <th>PP</th>
                                    <th>Puntos</th>
                                    <th>JF</th>
                                    <th>JC</th>
                                    <th>Dif.</th>
                                    <th>Anonimizada</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse ($officialResult->leagueRows as $row)
                                    <tr>
                                        <td>{{ $row->position }}</td>
                                        <td>{{ $row->source_entry_id }}</td>
                                        <td>{{ $row->display_name_snapshot }}</td>
                                        <td>{{ $row->public_display_name }}</td>
                                        <td>{{ $row->identity_projection?->value ?? '—' }}</td>
                                        <td>{{ $row->played }}</td>
                                        <td>{{ $row->wins }}</td>
                                        <td>{{ $row->losses }}</td>
                                        <td><strong>{{ $row->points }}</strong></td>
                                        <td>{{ $row->games_for }}</td>
                                        <td>{{ $row->games_against }}</td>
                                        <td>{{ $row->games_diff }}</td>
                                        <td>{{ $row->public_anonymized_at?->format('d/m/Y H:i:s') ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="13" class="text-center text-secondary">
                                            Esta versión no contiene filas de clasificación persistidas.
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="col-12">
                <div class="card page-card">
                    <div class="card-body">
                        <h2 class="h4 section-title">Campeón persistido</h2>

                        @if ($officialResult->cupWinner)
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle mb-0">
                                    <thead class="table-dark">
                                    <tr>
                                        <th>Source entry</th>
                                        <th>Source final match</th>
                                        <th>Identidad histórica</th>
                                        <th>Identidad pública</th>
                                        <th>Proyección</th>
                                        <th>Anonimizada</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr>
                                        <td>{{ $officialResult->cupWinner->source_entry_id }}</td>
                                        <td>{{ $officialResult->cupWinner->source_final_match_id }}</td>
                                        <td>{{ $officialResult->cupWinner->display_name_snapshot }}</td>
                                        <td>{{ $officialResult->cupWinner->public_display_name }}</td>
                                        <td>{{ $officialResult->cupWinner->identity_projection?->value ?? '—' }}</td>
                                        <td>{{ $officialResult->cupWinner->public_anonymized_at?->format('d/m/Y H:i:s') ?? '—' }}</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="alert alert-warning mb-0">
                                Esta versión no contiene un campeón persistido.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="col-12">
            <div class="card page-card">
                <div class="card-body">
                    <h2 class="h4 section-title">Evidencia de partidos persistida</h2>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0">
                            <thead class="table-dark">
                            <tr>
                                <th>Fase</th>
                                <th>Source match</th>
                                <th>Source round</th>
                                <th>Local</th>
                                <th>Marcador</th>
                                <th>Visitante</th>
                                <th>Ganador</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse ($officialResult->matchSnapshots as $snapshot)
                                <tr>
                                    <td>{{ $snapshot->stage }}</td>
                                    <td>{{ $snapshot->source_game_match_id }}</td>
                                    <td>{{ $snapshot->source_round_id }}</td>
                                    <td>{{ $snapshot->home_entry_id }}</td>
                                    <td>{{ $snapshot->home_score }} - {{ $snapshot->away_score }}</td>
                                    <td>{{ $snapshot->away_entry_id }}</td>
                                    <td>{{ $snapshot->winner_entry_id }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-secondary">
                                        Esta versión no contiene evidencia de partidos persistida.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
