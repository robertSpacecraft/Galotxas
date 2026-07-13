@if ($report)
    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
        <strong class="fs-5">{{ $report->home_score }} - {{ $report->away_score }}</strong>
        <span class="badge text-bg-danger">Conflicto</span>
    </div>

    <div class="small text-secondary mb-2">
        Reportado por
        <strong>
            {{ $report->player?->nickname
                ?: (trim(($report->player?->user?->name ?? '') . ' ' . ($report->player?->user?->lastname ?? ''))
                    ?: 'Jugador sin nombre') }}
        </strong>
    </div>

    <div class="mb-2">
        <span class="fw-semibold">Comentario:</span>
        {{ $report->comment ?: 'Sin comentario.' }}
    </div>

    <time class="small text-secondary" datetime="{{ $report->created_at?->toISOString() }}">
        {{ $report->created_at?->format('d/m/Y H:i') ?: 'Fecha no disponible' }}
    </time>
@else
    <div class="alert alert-warning mb-0 py-2">
        No se ha encontrado el reporte de este lado.
    </div>
@endif
