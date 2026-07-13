@extends('admin.layout')

@section('title', 'Resolver conflicto de resultado')

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Resolver conflicto de resultado</h1>
            <p class="text-secondary mb-0">
                Revisa las dos versiones antes de establecer el tanteo oficial.
            </p>
        </div>

        <a href="{{ route('admin.match-conflicts.index') }}" class="btn btn-outline-secondary">
            Volver a conflictos
        </a>
    </div>

    @error('scores')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror

    <div class="card page-card mb-4">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Contexto del partido</h2>
        </div>
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Campeonato</dt>
                <dd class="col-sm-9">{{ $gameMatch->round?->category?->championship?->name ?: 'No disponible' }}</dd>

                <dt class="col-sm-3">Categoría</dt>
                <dd class="col-sm-9">{{ $gameMatch->round?->category?->name ?: 'No disponible' }}</dd>

                <dt class="col-sm-3">Jornada</dt>
                <dd class="col-sm-9">{{ $gameMatch->round?->name ?: 'No disponible' }}</dd>

                <dt class="col-sm-3">Participantes</dt>
                <dd class="col-sm-9">
                    @include('admin.match-conflicts._entry-name', ['entry' => $gameMatch->homeEntry])
                    vs.
                    @include('admin.match-conflicts._entry-name', ['entry' => $gameMatch->awayEntry])
                </dd>

                <dt class="col-sm-3">Fecha</dt>
                <dd class="col-sm-9">{{ $gameMatch->scheduled_date?->format('d/m/Y H:i') ?: 'Sin fecha' }}</dd>

                <dt class="col-sm-3">Pista</dt>
                <dd class="col-sm-9">{{ $gameMatch->venue?->name ?: 'Sin pista' }}</dd>

                <dt class="col-sm-3">Estado</dt>
                <dd class="col-sm-9"><span class="badge text-bg-info">En revisión</span></dd>
            </dl>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <section class="card page-card h-100" aria-labelledby="home-report-title">
                <div class="card-header bg-white">
                    <h2 id="home-report-title" class="h5 mb-0">Reporte local</h2>
                </div>
                <div class="card-body">
                    @include('admin.match-conflicts._report', ['report' => $gameMatch->homeResultReport])
                </div>
            </section>
        </div>

        <div class="col-lg-6">
            <section class="card page-card h-100" aria-labelledby="away-report-title">
                <div class="card-header bg-white">
                    <h2 id="away-report-title" class="h5 mb-0">Reporte visitante</h2>
                </div>
                <div class="card-body">
                    @include('admin.match-conflicts._report', ['report' => $gameMatch->awayResultReport])
                </div>
            </section>
        </div>
    </div>

    <div class="card page-card">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Resultado oficial</h2>
        </div>
        <div class="card-body">
            <div class="alert alert-warning">
                La resolución es definitiva. Los reportes originales se conservarán y tu usuario quedará registrado
                como administrador validador. El tanteo objetivo de esta modalidad es {{ $targetScore }}.
            </div>

            <form
                method="POST"
                action="{{ route('admin.match-conflicts.resolve', $gameMatch) }}"
                onsubmit="return confirm('¿Confirmas este tanteo como resultado oficial? Esta acción no puede repetirse.');"
            >
                @csrf

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="home_score" class="form-label">Tanteo oficial local</label>
                        <input
                            id="home_score"
                            name="home_score"
                            type="number"
                            min="0"
                            max="{{ $targetScore }}"
                            required
                            value="{{ old('home_score') }}"
                            class="form-control @error('home_score') is-invalid @enderror"
                        >
                        @error('home_score')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="away_score" class="form-label">Tanteo oficial visitante</label>
                        <input
                            id="away_score"
                            name="away_score"
                            type="number"
                            min="0"
                            max="{{ $targetScore }}"
                            required
                            value="{{ old('away_score') }}"
                            class="form-control @error('away_score') is-invalid @enderror"
                        >
                        @error('away_score')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button type="submit" class="btn btn-success">Confirmar resolución</button>
                    <a href="{{ route('admin.match-conflicts.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
