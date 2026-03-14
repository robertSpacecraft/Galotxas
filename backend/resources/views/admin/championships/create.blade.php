@extends('admin.layout')

@section('content')

    <h1>Nuevo campeonato para la temporada: {{ $season->name }}</h1>

    @if ($errors->any())
        <div>
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.championships.store', $season) }}">
        @csrf

        <div style="margin-bottom: 1rem;">
            <label for="name">Nombre</label><br>
            <input
                id="name"
                type="text"
                name="name"
                value="{{ old('name') }}"
                required
            >
        </div>

        <div style="margin-bottom: 1rem;">
            <label for="type">Tipo</label><br>
            <select id="type" name="type" required>
                <option value="">Selecciona un tipo</option>
                <option value="singles" {{ old('type') === 'singles' ? 'selected' : '' }}>Singles</option>
                <option value="doubles" {{ old('type') === 'doubles' ? 'selected' : '' }}>Doubles</option>
            </select>
        </div>

        <button type="submit">Guardar campeonato</button>
    </form>

    <p style="margin-top: 1rem;">
        <a href="{{ route('admin.seasons.championships', $season) }}">Volver al listado</a>
    </p>

@endsection
