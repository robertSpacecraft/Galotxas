@extends('admin.layout')

@section('content')

    <div class="container mt-4">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">{{ $championship->name }}</h1>
                <p class="text-secondary mb-0">Detalle y ranking del campeonato</p>
            </div>

            <div class="d-flex gap-2">
                <a href="{{ route('admin.seasons.championships', $championship->season) }}"
                   class="btn btn-outline-secondary">
                    Volver a temporada
                </a>

                <a href="{{ route('admin.championships.categories', $championship) }}"
                   class="btn btn-outline-primary">
                    Ver categorías
                </a>
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

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="border rounded p-3 bg-light">
                    <div class="small text-secondary">ID</div>
                    <div class="fw-semibold">{{ $championship->id }}</div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="border rounded p-3 bg-light">
                    <div class="small text-secondary">Nombre</div>
                    <div class="fw-semibold">{{ $championship->name }}</div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="border rounded p-3 bg-light">
                    <div class="small text-secondary">Tipo</div>
                    <div class="fw-semibold">{{ $championship->type?->value ?? $championship->type }}</div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="border rounded p-3 bg-light">
                    <div class="small text-secondary">Temporada</div>
                    <div class="fw-semibold">{{ $championship->season?->name }}</div>
                </div>
            </div>

            <div class="col-12">
                <div class="border rounded p-3 bg-light">
                    <div class="small text-secondary">Categorías del campeonato</div>
                    <div class="fw-semibold">
                        {{ $championship->categories->pluck('name')->join(', ') ?: 'Sin categorías' }}
                    </div>
                </div>
            </div>
        </div>

        @php
            $pendingRegistrationRequests = $registrationRequests->filter(function ($registrationRequest) {
                $status = $registrationRequest->status;
                $statusValue = $status instanceof \BackedEnum ? $status->value : (string) $status;

                return $statusValue === 'pending';
            });

            $approvedRegistrationRequests = $registrationRequests->filter(function ($registrationRequest) {
                $status = $registrationRequest->status;
                $statusValue = $status instanceof \BackedEnum ? $status->value : (string) $status;

                return $statusValue === 'approved';
            });

            $rejectedRegistrationRequests = $registrationRequests->filter(function ($registrationRequest) {
                $status = $registrationRequest->status;
                $statusValue = $status instanceof \BackedEnum ? $status->value : (string) $status;

                return $statusValue === 'rejected';
            });
        @endphp

        @foreach ([$pendingRegistrationRequests, $approvedRegistrationRequests, $rejectedRegistrationRequests] as $registrationRequestGroup)
            @php
                $isPendingGroup = $loop->first;
                $isApprovedGroup = $loop->iteration === 2;
                $isRejectedGroup = $loop->last;
            @endphp
        <div class="card page-card mt-4">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                    <div>
                        <h2 class="h4 mb-1">{{ $isPendingGroup ? 'Solicitudes pendientes' : ($isApprovedGroup ? 'Solicitudes aprobadas' : 'Solicitudes rechazadas') }}</h2>
                        <p class="text-secondary mb-0">
                            {{ $isPendingGroup ? 'Solicitudes de inscripción que requieren revisión' : ($isApprovedGroup ? 'Jugadores cuya inscripción ya ha sido aprobada' : 'Solicitudes rechazadas que pueden volver a revisión') }}
                        </p>
                    </div>

                    @if ($isPendingGroup)
                    <form method="POST"
                          action="{{ route('admin.championships.registration-requests.approve-all', $championship) }}"
                          onsubmit="return confirm('¿Aprobar todas las solicitudes pendientes de este campeonato?')">
                        @csrf
                        <button type="submit" class="btn btn-success">
                            Aprobar todas las pendientes
                        </button>
                    </form>
                    @endif
                </div>

                @if ($registrationRequestGroup->isEmpty())
                    <div class="alert {{ $isPendingGroup ? 'alert-info' : 'alert-light border' }} mb-0">
                        {{ $isPendingGroup ? 'No hay solicitudes pendientes.' : ($isApprovedGroup ? 'No hay solicitudes aprobadas.' : 'No hay solicitudes rechazadas.') }}
                    </div>
                @else
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle mb-0">
                        <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Jugador</th>
                            <th>Email</th>
                            <th>Categoría sugerida</th>
                            <th>Estado</th>
                            <th>Pago</th>
                            <th>Comentario</th>
                            <th class="text-center" style="width: 240px;">Acciones</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($registrationRequestGroup as $registrationRequest)
                            @php
                                $playerName = $registrationRequest->player
                                    ? ($registrationRequest->player->nickname ?: (($registrationRequest->player->user->name ?? '') . ' ' . ($registrationRequest->player->user->lastname ?? '')))
                                    : (($registrationRequest->user->name ?? '') . ' ' . ($registrationRequest->user->lastname ?? ''));
                            @endphp
                            <tr>
                                <td>{{ $registrationRequest->id }}</td>
                                <td>{{ trim($playerName) ?: '-' }}</td>
                                <td>{{ $registrationRequest->user->email ?? '-' }}</td>
                                <td>{{ $registrationRequest->suggestedCategory->name ?? '-' }}</td>
                                <td>
                                    @php
                                        $requestStatus = $registrationRequest->status;
                                        $status = $requestStatus instanceof \BackedEnum
                                            ? $requestStatus->value
                                            : (string) $requestStatus;
                                        $statusLabel = is_object($requestStatus) && method_exists($requestStatus, 'label')
                                            ? $requestStatus->label()
                                            : ucfirst($status);
                                    @endphp

                                    @if ($status === 'approved')
                                        <span class="badge text-bg-success">{{ $statusLabel }}</span>
                                    @elseif ($status === 'rejected')
                                        <span class="badge text-bg-danger">{{ $statusLabel }}</span>
                                    @elseif ($status === 'cancelled')
                                        <span class="badge text-bg-secondary">{{ $statusLabel }}</span>
                                    @else
                                        <span class="badge text-bg-warning">{{ $statusLabel }}</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $paymentStatus = $registrationRequest->payment_status?->value ?? $registrationRequest->payment_status;
                                        $paymentStatusLabel = $registrationRequest->payment_status?->label() ?? ucfirst((string) $paymentStatus);
                                    @endphp

                                    <form method="POST"
                                          action="{{ route('admin.championships.registration-requests.update-payment-status', [$championship, $registrationRequest]) }}"
                                          class="d-flex gap-2 align-items-center">
                                        @csrf

                                        <select name="payment_status" class="form-select form-select-sm" onchange="this.form.submit()">
                                            <option value="pending" {{ $paymentStatus === 'pending' ? 'selected' : '' }}>Pendiente</option>
                                            <option value="paid" {{ $paymentStatus === 'paid' ? 'selected' : '' }}>Pagado</option>
                                            <option value="failed" {{ $paymentStatus === 'failed' ? 'selected' : '' }}>Fallido</option>
                                            <option value="refunded" {{ $paymentStatus === 'refunded' ? 'selected' : '' }}>Reembolsado</option>
                                            <option value="not_required" {{ $paymentStatus === 'not_required' ? 'selected' : '' }}>No requerido</option>
                                        </select>
                                    </form>
                                </td>
                                <td>{{ $registrationRequest->comment ?: '-' }}</td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                                        @if ($isPendingGroup)
                                            <form method="POST"
                                                  action="{{ route('admin.championships.registration-requests.approve', [$championship, $registrationRequest]) }}"
                                                  onsubmit="return confirm('¿Aprobar esta solicitud?')">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-success">
                                                    Aprobar
                                                </button>
                                            </form>
                                        @endif

                                        @if ($isRejectedGroup)
                                            <form method="POST"
                                                  action="{{ route('admin.championships.registration-requests.mark-as-pending', [$championship, $registrationRequest]) }}"
                                                  onsubmit="return confirm('¿Devolver esta solicitud al estado pendiente?')">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-warning">
                                                    Devolver a pendiente
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST"
                                                  action="{{ route('admin.championships.registration-requests.reject', [$championship, $registrationRequest]) }}"
                                                  onsubmit="return confirm('¿Rechazar esta solicitud?')">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    Rechazar
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>

        @endforeach

        <div class="card page-card">
            <div class="card-body">
                <h2 class="h4 section-title">Ranking de campeonato</h2>

                @if ($championshipRanking->isEmpty())
                    <div class="alert alert-warning mb-0">
                        No hay datos suficientes para calcular el ranking del campeonato.
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
                                <th>Puntos</th>
                                <th>Puntos ponderados</th>
                                <th>JF</th>
                                <th>JC</th>
                                <th>Dif.</th>
                                <th>Categorías</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($championshipRanking as $row)
                                <tr>
                                    <td>{{ $row['position'] }}</td>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ $row['played'] }}</td>
                                    <td>{{ $row['wins'] }}</td>
                                    <td>{{ $row['losses'] }}</td>
                                    <td>{{ number_format($row['raw_points'], 2, ',', '.') }}</td>
                                    <td>{{ number_format($row['weighted_points'], 2, ',', '.') }}</td>
                                    <td>{{ number_format($row['games_for'], 2, ',', '.') }}</td>
                                    <td>{{ number_format($row['games_against'], 2, ',', '.') }}</td>
                                    <td>{{ number_format($row['games_diff'], 2, ',', '.') }}</td>
                                    <td>{{ implode(', ', $row['categories_played_list']) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 small text-secondary">
                        Orden: puntos ponderados, victorias, diferencia de juegos y juegos a favor.
                    </div>
                @endif
            </div>
        </div>

    </div>

@endsection
