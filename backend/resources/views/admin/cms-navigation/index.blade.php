@extends('admin.layout')

@section('content')
    <div class="container-fluid px-0">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Navegación CMS</h1>
                <p class="text-secondary mb-0">
                    Añade páginas CMS al final del menú Club sin modificar la navegación estructural.
                </p>
            </div>
            <a href="{{ route('admin.cms-navigation.create') }}" class="btn btn-primary">
                Añadir página CMS
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif

        <div class="card shadow-sm mb-4">
            <div class="card-header fw-bold">Slot Club — destinos estructurales protegidos</div>
            <div class="card-body">
                <p class="text-secondary">
                    Estos cuatro elementos siempre aparecen primero y no pueden ocultarse, sustituirse ni reordenarse desde el CMS.
                </p>
                <ol class="mb-0">
                    @foreach ($structuralItems as $structuralItem)
                        <li>{{ $structuralItem }}</li>
                    @endforeach
                </ol>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header fw-bold">Páginas CMS añadidas después de los destinos estructurales</div>
            @if ($items->isEmpty())
                <div class="card-body">
                    <div class="alert alert-info mb-0">
                        No hay páginas CMS asignadas al menú Club.
                    </div>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th scope="col">Etiqueta</th>
                            <th scope="col">Página CMS</th>
                            <th scope="col">Estado página</th>
                            <th scope="col">Activo</th>
                            <th scope="col" class="text-end">Orden</th>
                            <th scope="col" class="text-end">Acciones</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($items as $item)
                            @php($publicationState = $item->cmsPage->publicationState())
                            <tr>
                                <td><strong>{{ $item->label }}</strong></td>
                                <td>
                                    {{ $item->cmsPage->title }}
                                    <div class="small text-secondary">/contenidos/{{ $item->cmsPage->slug }}</div>
                                </td>
                                <td>
                                    <span class="badge {{ $publicationState->badgeClass() }}">
                                        {{ $publicationState->label() }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $item->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                        {{ $item->is_active ? 'Sí' : 'No' }}
                                    </span>
                                </td>
                                <td class="text-end">{{ $item->sort_order }}</td>
                                <td>
                                    <div class="d-flex justify-content-end gap-2">
                                        <a
                                            href="{{ route('admin.cms-navigation.edit', $item) }}"
                                            class="btn btn-sm btn-outline-secondary"
                                        >
                                            Editar
                                        </a>
                                        <form
                                            method="POST"
                                            action="{{ route('admin.cms-navigation.destroy', $item) }}"
                                            onsubmit="return confirm('¿Eliminar este elemento del menú? La página CMS no se borrará.')"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
