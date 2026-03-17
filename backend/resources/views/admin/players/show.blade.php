@extends('admin.layout')

@section('content')

    <h1>Detalle de jugador</h1>

    <p><strong>ID:</strong> {{ $player->id }}</p>
    <p><strong>Apodo:</strong> {{ $player->nickname ?: '—' }}</p>
    <p><strong>Slug:</strong> {{ $player->slug }}</p>
    <p><strong>DNI:</strong> {{ $player->dni ?: '—' }}</p>
    <p><strong>Fecha de nacimiento:</strong> {{ $player->birth_date ? $player->birth_date->format('d/m/Y') : '—' }}</p>
    <p><strong>Género:</strong> {{ $player->gender?->label() ?? '—' }}</p>
    <p><strong>Nivel:</strong> {{ $player->level }}</p>
    <p><strong>Número de licencia:</strong> {{ $player->license_number ?: '—' }}</p>
    <p><strong>Mano dominante:</strong>
        @switch($player->dominant_hand)
            @case('right')
                Derecha
                @break
            @case('left')
                Izquierda
                @break
            @case('both')
                Ambas
                @break
            @default
                —
        @endswitch
    </p>
    <p><strong>Notas:</strong> {{ $player->notes ?: '—' }}</p>
    <p><strong>Activo:</strong> {{ $player->active ? 'Sí' : 'No' }}</p>

    <hr>

    <h2>Usuario asociado</h2>
    <p><strong>Nombre:</strong> {{ trim(($player->user?->name ?? '') . ' ' . ($player->user?->lastname ?? '')) ?: '—' }}</p>
    <p><strong>Email:</strong> {{ $player->user?->email ?? '—' }}</p>

    @if($player->user)
        <p>
            <a href="{{ route('admin.users.show', $player->user) }}">
                Ver usuario asociado
            </a>
        </p>
    @endif

    <br>

    <a href="{{ route('admin.players.index') }}">Volver</a>
    |
    <a href="{{ route('admin.players.edit', $player) }}">Editar</a>

@endsection
