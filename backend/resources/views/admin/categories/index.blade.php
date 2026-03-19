@extends('admin.layout')

@section('content')

    <h1>Categorías - {{ $championship->name }}</h1>

    @if (session('success'))
        <p>{{ session('success') }}</p>
    @endif

    <p>
        <a href="{{ route('admin.categories.create', $championship) }}">
            Crear categoría
        </a>
    </p>

    <table border="1" cellpadding="8">
        <thead>
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Nivel</th>
            <th>Género</th>
            <th>Acciones</th>
        </tr>
        </thead>

        <tbody>
        @forelse ($categories as $category)
            <tr>
                <td>{{ $category->id }}</td>
                <td>{{ $category->name }}</td>
                <td>{{ $category->level }}</td>
                <td>{{ $category->gender?->label() }}</td>
                <td>
                    <a href="{{ route('admin.categories.edit', $category) }}">
                        Editar
                    </a>

                    <form
                        method="POST"
                        action="{{ route('admin.categories.destroy', $category) }}"
                        style="display:inline"
                        onsubmit="return confirm('¿Eliminar categoría?')"
                    >
                        @csrf
                        @method('DELETE')

                        <button type="submit">
                            Eliminar
                        </button>
                    </form>

                    <a href="{{ route('admin.categories.show', $category) }}">
                        Ver
                    </a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5">No hay categorías registradas.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

@endsection
