@extends('admin.layout')

@section('content')

    <div class="container mt-4">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Ranking histórico</h1>
                <p class="text-secondary mb-0">
                    Clasificación acumulada de toda la trayectoria competitiva
                </p>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif

        <div class="alert alert-info">
            Para obtener posición oficial en el ranking debes haber jugado al menos <strong>{{ $minimumMatches }}</strong> partidos.
        </div>

        <div class="card page-card">
            <div class="card-body">
                <h2 class="h4 section-title">Clasificación histórica</h2>

                @if ($historicalRanking->isEmpty())
                    <div class="alert alert-warning mb-0">
                        No hay datos suficientes para calcular el ranking histórico.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0">
                            <thead class="table-dark">
                            <tr>
                                <th>Puesto</th>
                                <th>Jugador</th>
                                <th>PJ</th>
                                <th>PG</th>
                                <th>PP</th>
                                <th>% Victorias</th>
                                <th>PJ S</th>
                                <th>PJ D</th>
                                <th>Puntos</th>
                                <th>Puntos ponderados</th>
                                <th>Puntos/partido</th>
                                <th>JF</th>
                                <th>JC</th>
                                <th>Dif./partido</th>
                                <th>Estado ranking</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($historicalRanking as $row)
                                <tr>
                                    <td>
                                        @if (!is_null($row['position']))
                                            {{ $row['position'] }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ $row['played'] }}</td>
                                    <td>{{ $row['wins'] }}</td>
                                    <td>{{ $row['losses'] }}</td>
                                    <td>{{ number_format($row['win_rate'], 2, ',', '.') }}%</td>
                                    <td>{{ $row['played_singles'] }}</td>
                                    <td>{{ $row['played_doubles'] }}</td>
                                    <td>{{ number_format($row['raw_points'], 2, ',', '.') }}</td>
                                    <td>{{ number_format($row['weighted_points'], 2, ',', '.') }}</td>
                                    <td>{{ number_format($row['weighted_points_per_match'], 2, ',', '.') }}</td>
                                    <td>{{ number_format($row['games_for'], 2, ',', '.') }}</td>
                                    <td>{{ number_format($row['games_against'], 2, ',', '.') }}</td>
                                    <td>{{ number_format($row['games_diff_per_match'], 2, ',', '.') }}</td>
                                    <td>
                                        @if ($row['official_ranking'])
                                            <span class="badge text-bg-success">Oficial</span>
                                        @else
                                            <span class="badge text-bg-warning">
                                                Datos insuficientes ({{ $row['played'] }}/{{ $minimumMatches }})
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 small text-secondary">
                        <strong>Leyenda:</strong>
                        <ul class="mb-0">
                            <li><strong>PJ</strong>: Partidos jugados</li>
                            <li><strong>PG</strong>: Partidos ganados</li>
                            <li><strong>PP</strong>: Partidos perdidos</li>
                            <li><strong>% Victorias</strong>: Porcentaje de victorias sobre partidos jugados</li>
                            <li><strong>PJ S</strong>: Partidos jugados en modalidad singles</li>
                            <li><strong>PJ D</strong>: Partidos jugados en modalidad dobles</li>
                            <li><strong>Puntos</strong>: Puntos sin ponderar</li>
                            <li><strong>Puntos ponderados</strong>: Puntos ajustados por nivel y rol</li>
                            <li><strong>Puntos/partido</strong>: Media de puntos ponderados por partido</li>
                            <li><strong>JF</strong>: Juegos a favor</li>
                            <li><strong>JC</strong>: Juegos en contra</li>
                            <li><strong>Dif./partido</strong>: Diferencia de juegos media por partido</li>
                            <li><strong>Estado ranking</strong>: Indica si el jugador cumple el mínimo de partidos para posición oficial</li>
                        </ul>
                    </div>

                    <div class="mt-3 small text-secondary">
                        Orden oficial: puntos ponderados por partido, % de victorias, diferencia de juegos por partido y puntos ponderados totales.
                    </div>
                @endif
            </div>
        </div>

    </div>

@endsection
