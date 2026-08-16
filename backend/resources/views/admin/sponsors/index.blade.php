@extends('admin.layout')

@section('content')
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Colaboradores</h1>
                <p class="text-secondary mb-0">Patrocinadores institucionales mostrados antes del footer público.</p>
            </div>
            <a href="{{ route('admin.sponsors.create') }}" class="btn btn-primary">Crear colaborador</a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if ($sponsors->isEmpty())
            <div class="alert alert-info">No hay colaboradores registrados.</div>
        @else
            <div class="card page-card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0">
                            <thead class="table-dark">
                            <tr>
                                <th>Logo</th>
                                <th>Nombre</th>
                                <th>Web</th>
                                <th class="text-end">Orden</th>
                                <th>Estado efectivo</th>
                                <th>Ventana temporal</th>
                                <th class="text-center" style="width: 170px;">Acciones</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($sponsors as $sponsor)
                                @php($state = $sponsor->effectiveState())
                                <tr>
                                    <td>
                                        <img
                                            src="{{ route('admin.sponsors.logo', $sponsor) }}"
                                            alt="{{ $sponsor->name }}"
                                            width="{{ $sponsor->logo_width }}"
                                            height="{{ $sponsor->logo_height }}"
                                            style="width: 100px; height: 54px; object-fit: contain;"
                                        >
                                    </td>
                                    <td>{{ $sponsor->name }}</td>
                                    <td>
                                        @if ($sponsor->website_url)
                                            <a href="{{ $sponsor->website_url }}" target="_blank" rel="noopener noreferrer">
                                                {{ $sponsor->website_url }}
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="text-end">{{ $sponsor->sort_order }}</td>
                                    <td><span class="badge {{ $state->badgeClass() }}">{{ $state->label() }}</span></td>
                                    <td>
                                        <div>Desde: {{ $sponsor->starts_at?->format('d/m/Y H:i') ?? 'sin límite' }}</div>
                                        <div>Hasta: {{ $sponsor->ends_at?->format('d/m/Y H:i') ?? 'sin límite' }}</div>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-2">
                                            <a href="{{ route('admin.sponsors.edit', $sponsor) }}" class="btn btn-sm btn-outline-secondary">
                                                Editar
                                            </a>
                                            <form
                                                method="POST"
                                                action="{{ route('admin.sponsors.destroy', $sponsor) }}"
                                                onsubmit="return confirm('¿Eliminar este colaborador y su logo?')"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
