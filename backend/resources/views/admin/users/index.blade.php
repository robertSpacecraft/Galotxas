@extends('admin.layout')

@section('content')

    <h1>Usuarios</h1>

    <div style="margin-bottom: 16px;">
        <a href="{{ route('admin.users.create') }}">Crear usuario</a>
    </div>

    <form method="GET" action="{{ route('admin.users.index') }}" style="margin-bottom: 16px;">
        <label for="player_filter">Filtrar:</label>

        <select name="player_filter" id="player_filter">
            <option value="all" {{ $playerFilter === 'all' ? 'selected' : '' }}>Todos</option>
            <option value="with_player" {{ $playerFilter === 'with_player' ? 'selected' : '' }}>Solo jugadores</option>
            <option value="without_player" {{ $playerFilter === 'without_player' ? 'selected' : '' }}>Solo no jugadores</option>
        </select>

        <button type="submit">Aplicar</button>

        @if($playerFilter !== 'all')
            <a href="{{ route('admin.users.index') }}">Quitar filtro</a>
        @endif
    </form>

    @if(session('success'))
        <p style="color:green">{{ session('success') }}</p>
    @endif

    @if(session('error'))
        <p style="color:red">{{ session('error') }}</p>
    @endif

    <table border="1" cellpadding="6">

        <thead>
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Apellidos</th>
            <th>Email</th>
            <th>Rol</th>
            <th>Activo</th>
            <th>Jugador</th>
            <th>Acciones</th>
        </tr>
        </thead>

        <tbody>

        @foreach($users as $user)

            <tr>

                <td>{{ $user->id }}</td>

                <td>
                    <a href="{{ route('admin.users.show', $user) }}">
                        {{ $user->name }}
                    </a>
                </td>

                <td>{{ $user->lastname ?: '—' }}</td>

                <td>{{ $user->email }}</td>

                <td>{{ $user->role }}</td>

                <td>
                    @if($user->active)
                        Sí
                    @else
                        No
                    @endif
                </td>

                <td>
                    @if($user->player)
                        Sí
                    @else
                        No
                    @endif
                </td>

                <td>

                    <a href="{{ route('admin.users.show', $user) }}">
                        Ver
                    </a>

                    |

                    <a href="{{ route('admin.users.edit', $user) }}">
                        Editar
                    </a>

                    <form method="POST"
                          action="{{ route('admin.users.destroy', $user) }}"
                          style="display:inline">

                        @csrf
                        @method('DELETE')

                        <button type="submit"
                                onclick="return confirm('¿Seguro que deseas eliminar este usuario?')">
                            Eliminar
                        </button>

                    </form>

                </td>

            </tr>

        @endforeach

        </tbody>

    </table>

    <br>

    {{ $users->links() }}

@endsection
