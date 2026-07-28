@extends('admin.layout')

@section('content')
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Ubicaciones escolares</h1>
                <p class="text-secondary mb-0">
                    Espacios propios del dominio escolar, independientes de las pistas
                </p>
            </div>

            <a href="{{ route('admin.school.locations.create') }}" class="btn btn-primary">
                Crear ubicación
            </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if ($locations->isEmpty())
            <div class="alert alert-info">No hay ubicaciones escolares registradas.</div>
        @else
            <div class="card page-card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0">
                            <thead class="table-dark">
                            <tr>
                                <th>Nombre</th>
                                <th>Localidad</th>
                                <th>Dirección</th>
                                <th>Estado</th>
                                <th class="text-end">Programas</th>
                                <th class="text-end">Horarios</th>
                                <th class="text-end">Orden</th>
                                <th class="text-center" style="width: 180px;">Acciones</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($locations as $location)
                                @php
                                    $isInUse = $location->default_for_programs_count > 0
                                        || $location->schedules_count > 0;
                                @endphp
                                <tr>
                                    <td>{{ $location->name }}</td>
                                    <td>{{ $location->locality }}</td>
                                    <td>{{ $location->address ?: '-' }}</td>
                                    <td>
                                        <span class="badge {{ $location->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ $location->is_active ? 'Activa' : 'Inactiva' }}
                                        </span>
                                    </td>
                                    <td class="text-end">{{ $location->default_for_programs_count }}</td>
                                    <td class="text-end">{{ $location->schedules_count }}</td>
                                    <td class="text-end">{{ $location->sort_order }}</td>
                                    <td class="text-center">
                                        <div class="d-flex flex-wrap justify-content-center gap-2">
                                            <a
                                                href="{{ route('admin.school.locations.edit', $location) }}"
                                                class="btn btn-sm btn-outline-secondary"
                                            >
                                                Editar
                                            </a>

                                            @if ($isInUse)
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-danger"
                                                    disabled
                                                    title="La ubicación está asociada a programas u horarios"
                                                >
                                                    Eliminar
                                                </button>
                                            @else
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.school.locations.destroy', $location) }}"
                                                    onsubmit="return confirm('¿Eliminar esta ubicación escolar?')"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        Eliminar
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @if ($location->admin_notes)
                                    <tr>
                                        <td colspan="8">
                                            <strong>Notas administrativas:</strong>
                                            {{ $location->admin_notes }}
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
