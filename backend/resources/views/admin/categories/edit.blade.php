@extends('admin.layout')

@section('content')

    <h1>Editar categoría - {{ $category->name }}</h1>

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
            <select id="level" name="level" required>
                <option value="">Selecciona un nivel</option>
                @foreach ($levelOptions as $level)
                    <option value="{{ $level }}" {{ (string) old('level', $category->level) === (string) $level ? 'selected' : '' }}>
                        {{ $level }}
                    </option>
                @endforeach
            </select>
        </div>

        <div style="margin-bottom: 1rem;">
            <label for="gender">Género</label><br>
            <select id="gender" name="gender" required>
                <option value="">Selecciona un género</option>
                @foreach ($genderOptions as $option)
                    <option value="{{ $option['value'] }}" {{ old('gender', $category->gender?->value) === $option['value'] ? 'selected' : '' }}>
                        {{ $option['label'] }}
                    </option>
                @endforeach
            </select>
        </div>

        <button type="submit">Guardar cambios</button>
    </form>

    <p style="margin-top: 1rem;">
        <a href="{{ route('admin.championships.categories', $category->championship) }}">Volver al listado</a>
    </p>

@endsection
