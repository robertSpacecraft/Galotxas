@extends('admin.layout')

@section('content')

    <h1>Categorías - {{ $championship->name }}</h1>

    <a href="{{ route('admin.categories.create', $championship) }}">
        Crear categoría
    </a>

    <table border="1" cellpadding="8">

        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Nivel</th>
            <th>Acciones</th>
        </tr>

        @foreach ($categories as $category)

            <tr>
                <td>{{ $category->id }}</td>
                <td>{{ $category->name }}</td>
                <td>{{ $category->level }}</td>

                <td>

                    <a href="{{ route('admin.categories.edit', $category) }}">
                        Editar
                    </a>

                    <form method="POST"
                          action="{{ route('admin.categories.destroy',$category) }}"
                          style="display:inline">

                        @csrf
                        @method('DELETE')

                        <button type="submit">
                            Eliminar
                        </button>

                    </form>

                </td>

            </tr>

        @endforeach

    </table>

@endsection
