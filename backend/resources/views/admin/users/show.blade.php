@extends('admin.layout')

@section('content')

    <h1>Detalle de usuario</h1>

    <p><strong>ID:</strong> {{ $user->id }}</p>
    <p><strong>Nombre:</strong> {{ $user->name }}</p>
    <p><strong>Apellidos:</strong> {{ $user->lastname ?: '—' }}</p>
    <p><strong>Email:</strong> {{ $user->email }}</p>
    <p><strong>Rol:</strong> {{ $user->role }}</p>
    <p><strong>Activo:</strong> {{ $user->active ? 'Sí' : 'No' }}</p>
    <p><strong>Es jugador:</strong> {{ $user->player ? 'Sí' : 'No' }}</p>

    @if($user->player)
        <p>
            <strong>Perfil deportivo:</strong>
            <a href="{{ route('admin.players.show', $user->player) }}">
                Ver jugador asociado
            </a>
        </p>
    @endif

    <br>

    <a href="{{ route('admin.users.index') }}">Volver</a>
    |
    <a href="{{ route('admin.users.edit', $user) }}">Editar</a>

@endsection
