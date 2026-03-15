@extends('admin.layout')

@section('content')

    <h1>Campeonatos</h1>

    @if ($championships->isEmpty())
        <p>No hay campeonatos registrados.</p>
    @else
        <table border="1" cellpadding="8" cellspacing="0">
            <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Tipo</th>
                <th>Temporada</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($championships as $championship)
                <tr>
                    <td>{{ $championship->id }}</td>
                    <td>{{ $championship->name }}</td>
                    <td>{{ $championship->type }}</td>
                    <td>{{ $championship->season?->name }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

@endsection
