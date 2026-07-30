@extends('admin.layout')

@section('content')
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Centros educativos</h1>
                <p class="text-secondary mb-0">
                    Centros colaboradores y su histórico de actividades
                </p>
            </div>

            <a
                href="{{ route('admin.school.educational-centers.create') }}"
                class="btn btn-primary"
            >
                Crear centro
            </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card page-card mb-4">
            <div class="card-body">
                <form
                    method="GET"
                    action="{{ route('admin.school.educational-centers.index') }}"
                    class="row g-3 align-items-end"
                >
                    <div class="col-md-4">
                        <label for="active" class="form-label">Estado</label>
                        <select id="active" name="active" class="form-select">
                            <option value="">Todos</option>
                            <option value="1" @selected(($filters['active'] ?? null) === '1')>
                                Activos
                            </option>
                            <option value="0" @selected(($filters['active'] ?? null) === '0')>
                                Inactivos
                            </option>
                        </select>
                    </div>

                    <div class="col-md-5">
                        <label for="locality" class="form-label">Localidad</label>
                        <select id="locality" name="locality" class="form-select">
                            <option value="">Todas</option>
                            @foreach ($localities as $locality)
                                <option
                                    value="{{ $locality }}"
                                    @selected(($filters['locality'] ?? null) === $locality)
                                >
                                    {{ $locality }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                        <a
                            href="{{ route('admin.school.educational-centers.index') }}"
                            class="btn btn-outline-secondary"
                        >
                            Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </div>

        @if ($centers->isEmpty())
            <div class="alert alert-info">
                No hay centros educativos para los filtros seleccionados.
            </div>
        @else
            <div class="card page-card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0">
                            <thead class="table-dark">
                            <tr>
                                <th>Nombre</th>
                                <th>Localidad</th>
                                <th>Contacto</th>
                                <th>Estado</th>
                                <th class="text-end">Actividades</th>
                                <th>Última actividad</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($centers as $center)
                                <tr>
                                    <td>{{ $center->name }}</td>
                                    <td>{{ $center->locality }}</td>
                                    <td>
                                        @if ($center->contact_name)
                                            <div>{{ $center->contact_name }}</div>
                                        @endif
                                        @if ($center->contact_phone)
                                            <div>{{ $center->contact_phone }}</div>
                                        @endif
                                        @if ($center->contact_email)
                                            <div>{{ $center->contact_email }}</div>
                                        @endif
                                        @if (
                                            ! $center->contact_name
                                            && ! $center->contact_phone
                                            && ! $center->contact_email
                                        )
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $center->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ $center->is_active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td class="text-end">{{ $center->activities_count }}</td>
                                    <td>
                                        {{ $center->last_activity_date
                                            ? \Illuminate\Support\Carbon::parse($center->last_activity_date)->format('d/m/Y')
                                            : '-' }}
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex flex-wrap justify-content-center gap-2">
                                            <a
                                                href="{{ route('admin.school.educational-centers.show', $center) }}"
                                                class="btn btn-sm btn-outline-primary"
                                            >
                                                Ver
                                            </a>
                                            <a
                                                href="{{ route('admin.school.educational-centers.edit', $center) }}"
                                                class="btn btn-sm btn-outline-secondary"
                                            >
                                                Editar
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                {{ $centers->links() }}
            </div>
        @endif
    </div>
@endsection
