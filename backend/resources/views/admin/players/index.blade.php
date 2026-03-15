@extends('admin.layout')

@section('title', 'Jugadores')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Jugadores</h1>

        <a href="{{ route('admin.players.create') }}" class="btn btn-primary">
            Nuevo jugador
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if ($players->count())
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Email</th>
                            <th>DNI</th>
                            <th>Género</th>
                            <th>Nivel</th>
                            <th>Activo</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($players as $player)
                            <tr>
                                <td>{{ $player->id }}</td>
                                <td>{{ $player->user?->name ?? '—' }}</td>
                                <td>{{ $player->user?->email ?? '—' }}</td>
                                <td>{{ $player->dni ?: '—' }}</td>
                                <td>{{ $player->gender?->label() ?? '—' }}</td>
                                <td>{{ $player->level }}</td>
                                <td>
                                    @if ($player->active)
                                        <span class="badge bg-success">Sí</span>
                                    @else
                                        <span class="badge bg-secondary">No</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('admin.players.edit', $player) }}" class="btn btn-sm btn-outline-primary">
                                        Editar
                                    </a>

                                    <form action="{{ route('admin.players.destroy', $player) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('¿Seguro que quieres eliminar este jugador?');">
                                        @csrf
                                        @method('DELETE')

                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            Eliminar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-4">
            {{ $players->links() }}
        </div>
    @else
        <div class="alert alert-info mb-0">
            No hay jugadores registrados todavía.
        </div>
    @endif
@endsection
