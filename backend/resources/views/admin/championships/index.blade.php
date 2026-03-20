@extends('admin.layout')

@section('content')

    <div class="container mt-4">

        <h1 class="mb-4">Campeonatos</h1>

        @if ($championships->isEmpty())
            <div class="alert alert-info">
                No hay campeonatos registrados.
            </div>
        @else
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Temporada</th>
                    <th>Acciones</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($championships as $championship)
                    <tr>
                        <td>{{ $championship->id }}</td>
                        <td>{{ $championship->name }}</td>
                        <td>{{ $championship->type }}</td>
                        <td>{{ $championship->season?->name }}</td>
                        <td>
                            <a href="{{ route('admin.championships.show', $championship) }}"
                               class="btn btn-sm btn-primary">
                                Ver
                            </a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

    </div>

@endsection
