@extends('admin.layout')

@section('content')
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Solicitudes de contacto</h1>
                <p class="text-secondary mb-0">
                    Mensajes recibidos desde el formulario público cuando esté habilitado
                </p>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="row g-3 mb-4">
            @foreach ($statuses as $status)
                <div class="col-6 col-lg-4">
                    <div class="card page-card h-100">
                        <div class="card-body">
                            <div class="text-secondary">{{ $status->label() }}</div>
                            <div class="fs-3 fw-bold">{{ $counts[$status->value] }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card page-card mb-4">
            <div class="card-body">
                <form
                    method="GET"
                    action="{{ route('admin.contact-requests.index') }}"
                    class="row g-3 align-items-end"
                >
                    <div class="col-md-5">
                        <label for="status_filter" class="form-label">Estado</label>
                        <select id="status_filter" name="status" class="form-select">
                            <option value="">Todos</option>
                            @foreach ($statuses as $status)
                                <option
                                    value="{{ $status->value }}"
                                    @selected(($filters['status'] ?? '') === $status->value)
                                >
                                    {{ $status->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-7 d-flex gap-2">
                        <button type="submit" class="btn btn-outline-primary">Filtrar</button>
                        <a
                            href="{{ route('admin.contact-requests.index') }}"
                            class="btn btn-outline-secondary"
                        >
                            Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </div>

        @if ($contactRequests->isEmpty())
            <div class="alert alert-info">
                No hay solicitudes de contacto para los filtros seleccionados.
            </div>
        @else
            <div class="card page-card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0">
                            <thead class="table-dark">
                            <tr>
                                <th>Recibida</th>
                                <th>Nombre</th>
                                <th>Correo</th>
                                <th>Asunto</th>
                                <th>Estado</th>
                                <th class="text-center">Acción</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($contactRequests as $contactRequest)
                                <tr>
                                    <td>
                                        <time datetime="{{ $contactRequest->created_at->toIso8601String() }}">
                                            {{ $contactRequest->created_at->format('d/m/Y H:i') }}
                                        </time>
                                    </td>
                                    <td>{{ $contactRequest->name }}</td>
                                    <td>{{ $contactRequest->email }}</td>
                                    <td>{{ $contactRequest->subject }}</td>
                                    <td>
                                        <span class="badge {{ $contactRequest->status->badgeClass() }}">
                                            {{ $contactRequest->status->label() }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <a
                                            href="{{ route('admin.contact-requests.show', $contactRequest) }}"
                                            class="btn btn-sm btn-outline-primary"
                                        >
                                            Ver detalle
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        {{ $contactRequests->links() }}
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
