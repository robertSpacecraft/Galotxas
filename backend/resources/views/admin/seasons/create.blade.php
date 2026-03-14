@extends('admin.layout')

@section('content')

    <h1>Nueva temporada</h1>

    @if ($errors->any())
        <div>
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.seasons.store') }}">
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
            <label for="status">Estado</label><br>
            <select id="status" name="status" required>
                <option value="">Selecciona un estado</option>
                <option value="planned" {{ old('status') === 'planned' ? 'selected' : '' }}>Planned</option>
                <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="finished" {{ old('status') === 'finished' ? 'selected' : '' }}>Finished</option>
                <option value="cancelled" {{ old('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
        </div>

        <button type="submit">Guardar temporada</button>
    </form>

    <p style="margin-top: 1rem;">
        <a href="{{ route('admin.seasons.index') }}">Volver al listado</a>
    </p>

@endsection
