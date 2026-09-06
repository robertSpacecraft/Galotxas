@extends('admin.layout')

@section('content')
    @php
        $partLabel = $officialResult->competition_part === \App\Enums\OfficialResultCompetitionPart::LEAGUE
            ? 'Liga'
            : 'Copa';
    @endphp

    <div class="row justify-content-center">
        <div class="col-xl-8">
            <div class="card page-card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <h1 class="h3 mb-2">Reabrir resultado oficial de {{ $partLabel }}</h1>
                            <p class="text-secondary mb-0">
                                {{ $category->championship->season->name ?? 'Temporada sin nombre' }} ·
                                {{ $category->championship->name }} · {{ $category->name }}
                            </p>
                        </div>
                        <a href="{{ route('admin.categories.show', $category) }}" class="btn btn-outline-secondary">
                            Cancelar
                        </a>
                    </div>

                    <div class="alert alert-warning">
                        Vas a reabrir la versión v{{ $officialResult->version }}, oficializada el
                        {{ $officialResult->officialized_at?->format('d/m/Y H:i:s') ?? 'día no registrado' }}
                        por {{ $officialResult->officialized_by_name_snapshot ?: 'un actor no registrado' }}.
                        El histórico y su evidencia permanecerán inmutables, pero dejarán de bloquear las
                        mutaciones asociadas a {{ $partLabel }}.
                    </div>

                    <form
                        method="POST"
                        action="{{ route('admin.categories.official-results.reopen', [$category, $officialResult]) }}"
                        onsubmit="return confirm('¿Confirmas la reapertura de {{ $partLabel }} v{{ $officialResult->version }}?')"
                    >
                        @csrf

                        <div class="mb-3">
                            <label for="reason" class="form-label">Motivo de la reapertura</label>
                            <textarea
                                name="reason"
                                id="reason"
                                rows="6"
                                maxlength="2000"
                                required
                                class="form-control @error('reason') is-invalid @enderror"
                            >{{ old('reason') }}</textarea>
                            @error('reason')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Máximo 2000 caracteres.</div>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-danger">Confirmar reapertura</button>
                            <a
                                href="{{ route('admin.categories.official-results.show', [$category, $officialResult]) }}"
                                class="btn btn-outline-secondary"
                            >
                                Volver a la versión
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
