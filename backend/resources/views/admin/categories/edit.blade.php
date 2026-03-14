@extends('admin.layout')

@section('content')

    <h1>Editar categoría</h1>

    @if ($errors->any())
        <div>
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.categories.update', $category) }}">
        @csrf
        @method('PUT')

        <div style="margin-bottom: 1rem;">
            <label for="name">Nombre</label><br>
            <input
                id="name"
                type="text"
                name="name"
                value="{{ old('name', $category->name) }}"
                required
            >
        </div>

        <div style="margin-bottom: 1rem;">
            <label for="level">Nivel</label><br>
            <input
                id="level"
                type="number"
                name="level"
                value="{{ old('level', $category->level) }}"
                required
            >
        </div>

        <button type="submit">Guardar cambios</button>
    </form>

    <p style="margin-top: 1rem;">
        <a href="{{ route('admin.championships.categories', $category->championship) }}">Volver al listado</a>
    </p>

@endsection
