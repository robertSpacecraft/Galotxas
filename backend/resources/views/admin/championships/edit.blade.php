@extends('admin.layout')

@section('content')

    <h1>Editar campeonato</h1>

    <p>Temporada: {{ $championship->season->name }}</p>

    @if ($errors->any())
        <div>
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.championships.update', $championship) }}">
        @csrf
        @method('PUT')

        <div style="margin-bottom: 1rem;">
            <label for="name">Nombre</label><br>
            <input
                id="name"
                type="text"
                name="name"
                value="{{ old('name', $championship->name) }}"
                required
            >
        </div>

        <div style="margin-bottom: 1rem;">
            <label for="type">Tipo</label><br>
            <select id="type" name="type" required>
                <option value="singles" {{ old('type', $championship->type) === 'singles' ? 'selected' : '' }}>Singles</option>
                <option value="doubles" {{ old('type', $championship->type) === 'doubles' ? 'selected' : '' }}>Doubles</option>
            </select>
        </div>

        <button type="submit">Guardar cambios</button>
    </form>

    <p style="margin-top: 1rem;">
        <a href="{{ route('admin.seasons.championships', $championship->season) }}">Volver al listado</a>
    </p>

@endsection
