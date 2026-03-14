@extends('admin.layout')

@section('content')

    <h1>Editar temporada</h1>

    @if ($errors->any())
        @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    @endif

    <form method="POST" action="{{ route('admin.seasons.update', $season) }}">
        @csrf
        @method('PUT')

        <div>
            <label>Nombre</label><br>
            <input
                type="text"
                name="name"
                value="{{ old('name', $season->name) }}"
                required
            >
        </div>

        <div style="margin-top:1rem;">
            <label>Estado</label><br>
            <select name="status" required>
                <option value="planned" {{ $season->status === 'planned' ? 'selected' : '' }}>Planned</option>
                <option value="active" {{ $season->status === 'active' ? 'selected' : '' }}>Active</option>
                <option value="finished" {{ $season->status === 'finished' ? 'selected' : '' }}>Finished</option>
                <option value="cancelled" {{ $season->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
        </div>

        <div style="margin-top:1rem;">
            <button type="submit">Guardar cambios</button>
        </div>

    </form>

    <p style="margin-top:1rem;">
        <a href="{{ route('admin.seasons.index') }}">Volver</a>
    </p>

@endsection
