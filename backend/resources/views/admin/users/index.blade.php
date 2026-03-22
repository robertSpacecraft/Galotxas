@extends('admin.layout')

@section('title', 'Usuarios')

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-0">Usuarios</h1>
        </div>

        <div>
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
                Crear usuario
            </a>
        </div>
    </div>

    <div class="card page-card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.users.index') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="player_filter" class="form-label">Filtrar</label>

                    <select name="player_filter" id="player_filter" class="form-select">
                        <option value="all" {{ $playerFilter === 'all' ? 'selected' : '' }}>Todos</option>
                        <option value="with_player" {{ $playerFilter === 'with_player' ? 'selected' : '' }}>Solo jugadores</option>
                        <option value="without_player" {{ $playerFilter === 'without_player' ? 'selected' : '' }}>Solo no jugadores</option>
                    </select>
                </div>

                <div class="col-md-auto d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary">Aplicar</button>

                    @if($playerFilter !== 'all')
                        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
                            Quitar filtro
                        </a>
                    @endif
                </div>
            </form>
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

    <div class="card page-card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Apellidos</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Activo</th>
                        <th>Jugador</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    </thead>

                    <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td>{{ $user->id }}</td>

                            <td>
                                <a href="{{ route('admin.users.show', $user) }}" class="text-decoration-none fw-semibold">
                                    {{ $user->name }}
                                </a>
                            </td>

                            <td>{{ $user->lastname ?: '—' }}</td>

                            <td>{{ $user->email }}</td>

                            <td>
                                    <span class="badge text-bg-secondary">
                                        {{ $user->role }}
                                    </span>
                            </td>

                            <td>
                                @if($user->active)
                                    <span class="badge text-bg-success">Sí</span>
                                @else
                                    <span class="badge text-bg-danger">No</span>
                                @endif
                            </td>

                            <td>
                                @if($user->player)
                                    <span class="badge text-bg-primary">Sí</span>
                                @else
                                    <span class="badge text-bg-light">No</span>
                                @endif
                            </td>

                            <td>
                                <div class="d-flex justify-content-end gap-2 flex-wrap">
                                    <a href="{{ route('admin.users.show', $user) }}" class="btn btn-sm btn-outline-secondary">
                                        Ver
                                    </a>

                                    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-primary">
                                        Editar
                                    </a>

                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}">
                                        @csrf
                                        @method('DELETE')

                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('¿Seguro que deseas eliminar este usuario?')"
                                        >
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                No hay usuarios disponibles.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($users->hasPages())
        <div class="mt-4">
            {{ $users->links() }}
        </div>
    @endif
@endsection
