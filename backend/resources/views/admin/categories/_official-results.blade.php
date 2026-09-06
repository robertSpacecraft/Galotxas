{{-- Resultados oficiales --}}
<div class="col-12">
    <div class="card page-card">
        <div class="card-body">
            <div class="mb-4">
                <h2 class="h4 section-title mb-1">Resultados oficiales</h2>
                <p class="text-secondary mb-0">
                    Oficialización e histórico administrativo independiente de Liga y Copa.
                </p>
            </div>

            <div class="row g-3 mb-4">
                @foreach ($officialResults['parts'] as $partKey => $part)
                    <div class="col-lg-6">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                <h3 class="h5 mb-0">{{ $part['label'] }}</h3>

                                @if ($part['current'])
                                    <span class="badge text-bg-success">Oficial</span>
                                @elseif ($part['ready'])
                                    <span class="badge text-bg-info">Lista para oficializar</span>
                                @else
                                    <span class="badge text-bg-warning">No lista para oficializar</span>
                                @endif
                            </div>

                            @if ($part['current'])
                                <dl class="row small mb-3">
                                    <dt class="col-sm-5">Versión</dt>
                                    <dd class="col-sm-7">v{{ $part['current']->version }}</dd>
                                    <dt class="col-sm-5">Oficializado</dt>
                                    <dd class="col-sm-7">
                                        {{ $part['current']->officialized_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}
                                    </dd>
                                    <dt class="col-sm-5">Por</dt>
                                    <dd class="col-sm-7 mb-0">
                                        {{ $part['current']->officialized_by_name_snapshot ?: 'Sin actor registrado' }}
                                    </dd>
                                </dl>

                                <div class="alert alert-info py-2 small">
                                    Las mutaciones relacionadas con {{ $part['label'] }} permanecen bloqueadas
                                    hasta reabrir esta versión.
                                </div>

                                <div class="d-flex flex-wrap gap-2">
                                    <a
                                        href="{{ route('admin.categories.official-results.show', [$category, $part['current']]) }}"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        Ver versión
                                    </a>
                                    <a
                                        href="{{ route('admin.categories.official-results.reopen.form', [$category, $part['current']]) }}"
                                        class="btn btn-sm btn-outline-danger"
                                    >
                                        Reabrir
                                    </a>
                                </div>
                            @elseif ($part['ready'])
                                <p class="text-secondary">
                                    La fuente actual cumple todas las condiciones para crear una versión histórica.
                                </p>

                                <form
                                    method="POST"
                                    action="{{ route('admin.categories.official-results.'.$partKey.'.officialize', $category) }}"
                                    onsubmit="return confirm('¿Crear una versión histórica oficial de {{ $part['label'] }}? Las mutaciones relacionadas quedarán bloqueadas.')"
                                >
                                    @csrf
                                    <button type="submit" class="btn btn-success">
                                        Oficializar {{ $part['label'] }}
                                    </button>
                                </form>
                            @else
                                <p class="text-secondary mb-2">
                                    Corrige estas condiciones antes de oficializar:
                                </p>
                                <ul class="small mb-3">
                                    @foreach ($part['issues'] as $issue)
                                        <li>{{ $issue }}</li>
                                    @endforeach
                                </ul>
                                <button type="button" class="btn btn-outline-secondary" disabled aria-disabled="true">
                                    Oficializar {{ $part['label'] }}
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <h3 class="h5 mb-3">Histórico</h3>
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle mb-0">
                    <thead class="table-dark">
                    <tr>
                        <th>Parte</th>
                        <th>Versión</th>
                        <th>Estado</th>
                        <th>Oficializado</th>
                        <th>Por</th>
                        <th>Reabierto</th>
                        <th class="text-center">Acción</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($officialResults['history'] as $result)
                        <tr>
                            <td>
                                {{ $result->competition_part === \App\Enums\OfficialResultCompetitionPart::LEAGUE ? 'Liga' : 'Copa' }}
                            </td>
                            <td>v{{ $result->version }}</td>
                            <td>
                                <span class="badge {{ $result->status === \App\Enums\OfficialResultStatus::OFFICIAL ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $result->status === \App\Enums\OfficialResultStatus::OFFICIAL ? 'Oficial' : 'Reabierto' }}
                                </span>
                            </td>
                            <td>{{ $result->officialized_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td>{{ $result->officialized_by_name_snapshot ?: '—' }}</td>
                            <td>
                                @if ($result->reopened_at)
                                    {{ $result->reopened_at->format('d/m/Y H:i') }}
                                    · {{ $result->reopened_by_name_snapshot ?: 'Sin actor registrado' }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-center">
                                <a
                                    href="{{ route('admin.categories.official-results.show', [$category, $result]) }}"
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    Ver
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-secondary">
                                Todavía no hay versiones oficiales en esta categoría.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
