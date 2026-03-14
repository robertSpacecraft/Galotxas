@extends('admin.layout')

@section('content')

    <h1>Categorías</h1>

    @if ($categories->isEmpty())
        <p>No hay categorías registradas.</p>
    @else
        <table border="1" cellpadding="8" cellspacing="0">
            <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Nivel</th>
                <th>Campeonato</th>
                <th>Temporada</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($categories as $category)
                <tr>
                    <td>{{ $category->id }}</td>
                    <td>{{ $category->name }}</td>
                    <td>{{ $category->level }}</td>
                    <td>{{ $category->championship?->name }}</td>
                    <td>{{ $category->championship?->season?->name }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

@endsection
