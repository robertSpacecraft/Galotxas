@extends('admin.layout')

@section('content')

    <h1>Temporadas</h1>
    <p>
        <a href="{{ route('admin.seasons.create') }}">Crear nueva temporada</a>
    </p>
    @if (session('success'))
        <p>{{ session('success') }}</p>
    @endif

    @if ($seasons->isEmpty())
        <p>No hay temporadas registradas.</p>
    @else
        <table border="1" cellpadding="8" cellspacing="0">
            <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($seasons as $season)
                <tr>
                    <td>{{ $season->id }}</td>
                    <td>{{ $season->name }}</td>
                    <td>{{ $season->status }}</td>
                    <td>
                        <a href="{{ route('admin.seasons.edit', $season) }}">Editar</a>

                        <form
                            method="POST"
                            action="{{ route('admin.seasons.destroy', $season) }}"
                            style="display:inline"
                            onsubmit="return confirm('¿Eliminar temporada?')"
                        >
                            @csrf
                            @method('DELETE')

                            <button type="submit">Eliminar</button>

                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

@endsection
