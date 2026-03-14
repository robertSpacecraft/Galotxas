@extends('admin.layout')

@section('content')

    <h1>Campeonatos de la temporada: {{ $season->name }}</h1>

    <p>
        <a href="{{ route('admin.championships.create', $season) }}">Crear nuevo campeonato</a>
    </p>

    @if (session('success'))
        <p>{{ session('success') }}</p>
    @endif

    @if ($season->championships->isEmpty())
        <p>No hay campeonatos.</p>
    @else

        <table border="1" cellpadding="8">
            <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Tipo</th>
                <th>Acciones</th>
            </tr>
            </thead>

            <tbody>
            @foreach($season->championships as $championship)
                <tr>
                    <td>{{ $championship->id }}</td>
                    <td>{{ $championship->name }}</td>
                    <td>{{ $championship->type }}</td>
                    <td>
                        <a href="{{ route('admin.championships.edit', $championship) }}">Editar</a>

                        <form
                            method="POST"
                            action="{{ route('admin.championships.destroy', $championship) }}"
                            style="display:inline"
                            onsubmit="return confirm('¿Eliminar campeonato?')"
                        >
                            @csrf
                            @method('DELETE')
                            <button type="submit">Eliminar</button>
                        </form>

                        <span>Categorías próximamente</span>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

    @endif

    <p>
        <a href="{{ route('admin.seasons.index') }}">← Volver a temporadas</a>
    </p>

@endsection
