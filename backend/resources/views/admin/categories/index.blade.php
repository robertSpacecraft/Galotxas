@extends('admin.layout')

@section('title', 'Categorías del campeonato')

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Categorías - {{ $championship->name }}</h1>
            <p class="text-secondary mb-0">
                Gestión de categorías del campeonato
            </p>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('admin.championships.show', $championship) }}" class="btn btn-outline-secondary">
                Volver al campeonato
            </a>

            <a href="{{ route('admin.categories.create', $championship) }}" class="btn btn-primary">
                Crear categoría
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <div class="card page-card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Nivel</th>
                        <th>Género</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    </thead>

                    <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td>{{ $category->id }}</td>
                            <td class="fw-semibold">{{ $category->name }}</td>
                            <td>{{ $category->level }}</td>
                            <td>{{ $category->gender?->label() }}</td>
                            <td>
                                <div class="d-flex justify-content-end gap-2 flex-wrap">
                                    <a href="{{ route('admin.categories.show', $category) }}"
                                       class="btn btn-sm btn-outline-secondary">
                                        Ver
                                    </a>

                                    <a href="{{ route('admin.categories.edit', $category) }}"
                                       class="btn btn-sm btn-outline-primary">
                                        Editar
                                    </a>

                                    <form
                                        method="POST"
                                        action="{{ route('admin.categories.destroy', $category) }}"
                                        onsubmit="return confirm('¿Eliminar categoría?')"
                                    >
                                        @csrf
                                        @method('DELETE')

                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-secondary py-4">
                                No hay categorías registradas en este campeonato.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
