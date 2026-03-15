@extends('admin.layout')

@section('content')

    <h1>Usuarios</h1>

    <a href="{{ route('admin.users.create') }}">Crear usuario</a>

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
            <th>Email</th>
            <th>Rol</th>
            <th>Activo</th>
            <th>Player</th>
            <th>Acciones</th>
        </tr>
        </thead>

        <tbody>

        @foreach($users as $user)

            <tr>

                <td>{{ $user->id }}</td>

                <td>{{ $user->name }}</td>

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
