@extends('admin.layout')

@section('content')

    <div class="row g-4">
        <div class="col-12">
            <div class="card page-card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div>
                            <h1 class="h3 mb-2">Detalle de categoría</h1>
                            <p class="text-secondary mb-0">
                                Gestión de inscripciones, equipos, calendario y resultados
                            </p>
                        </div>

                        <div class="d-flex gap-2">
                            <a href="{{ route('admin.championships.categories', $category->championship) }}"
                               class="btn btn-outline-secondary">
                                Volver a categorías
                            </a>
                        </div>
                    </div>

                    <hr>

                    @if (session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <strong>Se han encontrado errores:</strong>
                            <ul class="mb-0 mt-2">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="border rounded p-3 bg-light">
                                <div class="small text-secondary">ID</div>
                                <div class="fw-semibold">{{ $category->id }}</div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="border rounded p-3 bg-light">
                                <div class="small text-secondary">Nombre</div>
                                <div class="fw-semibold">{{ $category->name }}</div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="border rounded p-3 bg-light">
                                <div class="small text-secondary">Nivel</div>
                                <div class="fw-semibold">{{ $category->level }}</div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="border rounded p-3 bg-light">
                                <div class="small text-secondary">Género</div>
                                <div class="fw-semibold">{{ $category->gender?->label() }}</div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="border rounded p-3 bg-light">
                                <div class="small text-secondary">Campeonato</div>
                                <div class="fw-semibold">{{ $category->championship->name }}</div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="border rounded p-3 bg-light">
                                <div class="small text-secondary">Tipo</div>
                                <div class="fw-semibold">{{ $category->championship->type?->value }}</div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="border rounded p-3 bg-light">
                                <div class="small text-secondary">Temporada</div>
                                <div class="fw-semibold">{{ $category->championship->season->name ?? '-' }}</div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="border rounded p-3 bg-light">
                                <div class="small text-secondary">Tanteo objetivo</div>
                                <div class="fw-semibold">
                                    {{ $category->championship->type?->value === 'doubles' ? '12 juegos' : '10 juegos' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Inscripciones --}}
        <div class="col-12">
            <div class="card page-card">
                <div class="card-body">
                    <h2 class="h4 section-title">Inscripciones</h2>

                    <form method="POST" action="{{ route('admin.categories.registrations.store', $category) }}" class="row g-3 align-items-end mb-4">
                        @csrf

                        <div class="col-md-6 col-lg-4">
                            <label for="player_id" class="form-label">Jugador</label>
                            <select name="player_id" id="player_id" class="form-select" required>
                                <option value="">Selecciona un jugador</option>
                                @foreach ($availablePlayers as $player)
                                    <option value="{{ $player->id }}" {{ old('player_id') == $player->id ? 'selected' : '' }}>
                                        {{ $player->nickname ?: ($player->user->name . ' ' . $player->user->lastname) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary">Inscribir jugador</button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0">
                            <thead class="table-dark">
                            <tr>
                                <th>ID inscripción</th>
                                <th>ID jugador</th>
                                <th>Jugador</th>
                                <th>Status</th>
                                <th class="text-center" style="width: 140px;">Acciones</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse ($registrations as $registration)
                                <tr>
                                    <td>{{ $registration->id }}</td>
                                    <td>{{ $registration->player->id }}</td>
                                    <td>
                                        {{ $registration->player->nickname ?: ($registration->player->user->name . ' ' . $registration->player->user->lastname) }}
                                    </td>
                                    <td>
                                        <span class="badge text-bg-secondary">{{ $registration->status }}</span>
                                    </td>
                                    <td class="text-center">
                                        <form
                                            method="POST"
                                            action="{{ route('admin.categories.registrations.destroy', [$category, $registration]) }}"
                                            onsubmit="return confirm('¿Eliminar inscripción?')"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-secondary">No hay jugadores inscritos en esta categoría.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Equipos --}}
        @if ($category->championship->type?->value === 'doubles')
            <div class="col-12">
                <div class="card page-card">
                    <div class="card-body">
                        <h2 class="h4 section-title">Equipos</h2>

                        <form method="POST" action="{{ route('admin.categories.teams.store', $category) }}" class="row g-3 align-items-end mb-4">
                            @csrf

                            <div class="col-md-4">
                                <label for="name" class="form-label">Nombre del equipo</label>
                                <input type="text" name="name" id="name" value="{{ old('name') }}" class="form-control" placeholder="Opcional">
                            </div>

                            <div class="col-md-3">
                                <label for="front_player_id" class="form-label">Delantero</label>
                                <select name="front_player_id" id="front_player_id" class="form-select" required>
                                    <option value="">Selecciona jugador</option>
                                    @foreach ($teamSelectablePlayers as $player)
                                        <option value="{{ $player->id }}" {{ old('front_player_id') == $player->id ? 'selected' : '' }}>
                                            {{ $player->nickname ?: ($player->user->name . ' ' . $player->user->lastname) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="back_player_id" class="form-label">Trasero</label>
                                <select name="back_player_id" id="back_player_id" class="form-select" required>
                                    <option value="">Selecciona jugador</option>
                                    @foreach ($teamSelectablePlayers as $player)
                                        <option value="{{ $player->id }}" {{ old('back_player_id') == $player->id ? 'selected' : '' }}>
                                            {{ $player->nickname ?: ($player->user->name . ' ' . $player->user->lastname) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary">Crear equipo</button>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle mb-0">
                                <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Delantero</th>
                                    <th>Trasero</th>
                                    <th class="text-center" style="width: 140px;">Acciones</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse ($teams as $team)
                                    @php
                                        $frontPlayer = $team->players->firstWhere('pivot.role_in_team', 'front');
                                        $backPlayer = $team->players->firstWhere('pivot.role_in_team', 'back');
                                    @endphp
                                    <tr>
                                        <td>{{ $team->id }}</td>
                                        <td>{{ $team->name }}</td>
                                        <td>
                                            {{ $frontPlayer ? ($frontPlayer->nickname ?: ($frontPlayer->user->name . ' ' . $frontPlayer->user->lastname)) : '-' }}
                                        </td>
                                        <td>
                                            {{ $backPlayer ? ($backPlayer->nickname ?: ($backPlayer->user->name . ' ' . $backPlayer->user->lastname)) : '-' }}
                                        </td>
                                        <td class="text-center">
                                            <form
                                                method="POST"
                                                action="{{ route('admin.categories.teams.destroy', [$category, $team]) }}"
                                                onsubmit="return confirm('¿Eliminar equipo?')"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-secondary">No hay equipos creados en esta categoría.</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Ranking de categoría --}}
        <div class="col-12">
            <div class="card page-card">
                <div class="card-body">
                    <h2 class="h4 section-title">Ranking de categoría</h2>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0">
                            <thead class="table-dark">
                            <tr>
                                <th>Puesto</th>
                                <th>Participante</th>
                                <th>PJ</th>
                                <th>PG</th>
                                <th>PP</th>
                                <th>Puntos</th>
                                <th>JF</th>
                                <th>JC</th>
                                <th>Dif.</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse ($categoryRanking as $row)
                                <tr>
                                    <td>{{ $row['position'] }}</td>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ $row['played'] }}</td>
                                    <td>{{ $row['wins'] }}</td>
                                    <td>{{ $row['losses'] }}</td>
                                    <td><strong>{{ $row['points'] }}</strong></td>
                                    <td>{{ $row['games_for'] }}</td>
                                    <td>{{ $row['games_against'] }}</td>
                                    <td>{{ $row['games_diff'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-secondary">
                                        No hay datos suficientes para calcular el ranking todavía.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 small text-secondary">
                        Criterios: puntos, enfrentamiento directo (si aplica), diferencia de juegos y juegos a favor.
                    </div>
                </div>
            </div>
        </div>

        {{-- Copa --}}
        <div class="col-12">
            <div class="card page-card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                        <h2 class="h4 section-title mb-0">Copa</h2>

                        <div class="d-flex gap-2">
                            <form method="POST" action="{{ route('admin.categories.generate-cup', $category) }}">
                                @csrf
                                <button type="submit"
                                        class="btn btn-warning"
                                        onclick="return confirm('¿Generar o regenerar las semifinales de copa desde el ranking actual?')">
                                    Generar copa
                                </button>
                            </form>

                            <form method="POST" action="{{ route('admin.categories.delete-cup', $category) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="btn btn-outline-danger"
                                        onclick="return confirm('¿Eliminar la copa actual?')">
                                    Eliminar copa
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.categories.generate-finals', $category) }}">
                                @csrf
                                <button type="submit"
                                        class="btn btn-success"
                                        onclick="return confirm('¿Generar final y 3º/4º desde semifinales validadas?')">
                                    Generar finales
                                </button>
                            </form>
                        </div>
                    </div>

                    @forelse ($cupRounds as $round)
                        <div class="mb-4">
                            <h3 class="h5 mb-3">{{ $round->name }}</h3>

                            <div class="table-responsive">
                                <table class="table table-bordered align-middle mb-0">
                                    <thead class="table-dark">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Hora</th>
                                        <th>Pista</th>
                                        <th>Local</th>
                                        <th>Marcador</th>
                                        <th>Visitante</th>
                                        <th>Status</th>
                                        <th class="text-center">Guardar</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($round->matches->sortBy('id') as $match)
                                        @php
                                            $formId = 'cup-match-form-' . $match->id;
                                        @endphp

                                        <form id="{{ $formId }}" method="POST" action="{{ route('admin.categories.matches.update', [$category, $match]) }}">
                                            @csrf
                                            @method('PATCH')
                                        </form>

                                        <tr class="match-form-row">
                                            <td>
                                                <input
                                                    type="date"
                                                    name="scheduled_date"
                                                    form="{{ $formId }}"
                                                    value="{{ $match->scheduled_date ? $match->scheduled_date->format('Y-m-d') : '' }}"
                                                    class="form-control form-control-sm"
                                                    required
                                                >
                                            </td>

                                            <td>
                                                <input
                                                    type="time"
                                                    name="scheduled_time"
                                                    form="{{ $formId }}"
                                                    value="{{ $match->scheduled_date ? $match->scheduled_date->format('H:i') : '' }}"
                                                    class="form-control form-control-sm"
                                                    required
                                                >
                                            </td>

                                            <td>
                                                <select name="venue_id" form="{{ $formId }}" class="form-select form-select-sm" required>
                                                    @foreach ($venues as $venue)
                                                        <option value="{{ $venue->id }}" {{ $match->venue_id == $venue->id ? 'selected' : '' }}>
                                                            {{ $venue->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>

                                            <td>
                                                @if ($match->homeEntry?->entry_type === 'player')
                                                    {{ $match->homeEntry->player->nickname ?: ($match->homeEntry->player->user->name . ' ' . $match->homeEntry->player->user->lastname) }}
                                                @elseif ($match->homeEntry?->entry_type === 'team')
                                                    {{ $match->homeEntry->team->name }}
                                                @else
                                                    -
                                                @endif
                                            </td>

                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        name="home_score"
                                                        form="{{ $formId }}"
                                                        value="{{ $match->home_score }}"
                                                        class="form-control form-control-sm"
                                                        style="max-width: 80px;"
                                                    >
                                                    <span>-</span>
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        name="away_score"
                                                        form="{{ $formId }}"
                                                        value="{{ $match->away_score }}"
                                                        class="form-control form-control-sm"
                                                        style="max-width: 80px;"
                                                    >
                                                </div>
                                            </td>

                                            <td>
                                                @if ($match->awayEntry?->entry_type === 'player')
                                                    {{ $match->awayEntry->player->nickname ?: ($match->awayEntry->player->user->name . ' ' . $match->awayEntry->player->user->lastname) }}
                                                @elseif ($match->awayEntry?->entry_type === 'team')
                                                    {{ $match->awayEntry->team->name }}
                                                @else
                                                    -
                                                @endif
                                            </td>

                                            <td>
                                                <select name="status" form="{{ $formId }}" class="form-select form-select-sm" required>
                                                    <option value="scheduled" {{ $match->status === 'scheduled' ? 'selected' : '' }}>scheduled</option>
                                                    <option value="submitted" {{ $match->status === 'submitted' ? 'selected' : '' }}>submitted</option>
                                                    <option value="validated" {{ $match->status === 'validated' ? 'selected' : '' }}>validated</option>
                                                    <option value="postponed" {{ $match->status === 'postponed' ? 'selected' : '' }}>postponed</option>
                                                    <option value="cancelled" {{ $match->status === 'cancelled' ? 'selected' : '' }}>cancelled</option>
                                                </select>
                                            </td>

                                            <td class="text-center">
                                                <button type="submit" form="{{ $formId }}" class="btn btn-sm btn-primary">
                                                    Guardar
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @empty
                        <div class="alert alert-secondary mb-0">
                            No hay copa generada todavía.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Liga --}}
        <div class="col-12">
            <div class="card page-card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                        <h2 class="h4 section-title mb-0">Liga</h2>

                        <form method="POST" action="{{ route('admin.categories.generate-league', $category) }}">
                            @csrf
                            <button type="submit" class="btn btn-success"
                                    onclick="return confirm('¿Generar la liga automáticamente?')">
                                Generar liga
                            </button>
                        </form>
                    </div>

                    @forelse ($leagueRounds as $round)
                        <div class="mb-4">
                            <h3 class="h5 mb-3">{{ $round->name }}</h3>

                            <div class="table-responsive">
                                <table class="table table-bordered align-middle mb-0">
                                    <thead class="table-dark">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Hora</th>
                                        <th>Pista</th>
                                        <th>Local</th>
                                        <th>Marcador</th>
                                        <th>Visitante</th>
                                        <th>Status</th>
                                        <th class="text-center">Guardar</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($round->matches->sortBy('scheduled_date') as $match)
                                        @php
                                            $formId = 'match-form-' . $match->id;
                                        @endphp

                                        <form id="{{ $formId }}" method="POST" action="{{ route('admin.categories.matches.update', [$category, $match]) }}">
                                            @csrf
                                            @method('PATCH')
                                        </form>

                                        <tr class="match-form-row">
                                            <td>
                                                <input
                                                    type="date"
                                                    name="scheduled_date"
                                                    form="{{ $formId }}"
                                                    value="{{ $match->scheduled_date ? $match->scheduled_date->format('Y-m-d') : '' }}"
                                                    class="form-control form-control-sm"
                                                    required
                                                >
                                            </td>

                                            <td>
                                                <input
                                                    type="time"
                                                    name="scheduled_time"
                                                    form="{{ $formId }}"
                                                    value="{{ $match->scheduled_date ? $match->scheduled_date->format('H:i') : '' }}"
                                                    class="form-control form-control-sm"
                                                    required
                                                >
                                            </td>

                                            <td>
                                                <select name="venue_id" form="{{ $formId }}" class="form-select form-select-sm" required>
                                                    @foreach ($venues as $venue)
                                                        <option value="{{ $venue->id }}" {{ $match->venue_id == $venue->id ? 'selected' : '' }}>
                                                            {{ $venue->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>

                                            <td>
                                                @if ($match->homeEntry->entry_type === 'player')
                                                    {{ $match->homeEntry->player->nickname ?: ($match->homeEntry->player->user->name . ' ' . $match->homeEntry->player->user->lastname) }}
                                                @else
                                                    {{ $match->homeEntry->team->name }}
                                                @endif
                                            </td>

                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        name="home_score"
                                                        form="{{ $formId }}"
                                                        value="{{ $match->home_score }}"
                                                        class="form-control form-control-sm"
                                                        style="max-width: 80px;"
                                                    >
                                                    <span>-</span>
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        name="away_score"
                                                        form="{{ $formId }}"
                                                        value="{{ $match->away_score }}"
                                                        class="form-control form-control-sm"
                                                        style="max-width: 80px;"
                                                    >
                                                </div>
                                            </td>

                                            <td>
                                                @if ($match->awayEntry->entry_type === 'player')
                                                    {{ $match->awayEntry->player->nickname ?: ($match->awayEntry->player->user->name . ' ' . $match->awayEntry->player->user->lastname) }}
                                                @else
                                                    {{ $match->awayEntry->team->name }}
                                                @endif
                                            </td>

                                            <td>
                                                <select name="status" form="{{ $formId }}" class="form-select form-select-sm" required>
                                                    <option value="scheduled" {{ $match->status === 'scheduled' ? 'selected' : '' }}>scheduled</option>
                                                    <option value="submitted" {{ $match->status === 'submitted' ? 'selected' : '' }}>submitted</option>
                                                    <option value="validated" {{ $match->status === 'validated' ? 'selected' : '' }}>validated</option>
                                                    <option value="postponed" {{ $match->status === 'postponed' ? 'selected' : '' }}>postponed</option>
                                                    <option value="cancelled" {{ $match->status === 'cancelled' ? 'selected' : '' }}>cancelled</option>
                                                </select>
                                            </td>

                                            <td class="text-center">
                                                <button type="submit" form="{{ $formId }}" class="btn btn-sm btn-primary">
                                                    Guardar
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @empty
                        <div class="alert alert-secondary mb-0">
                            No hay jornadas de liga generadas todavía.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

@endsection
