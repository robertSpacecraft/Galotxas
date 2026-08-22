@extends('admin.layout')

@section('content')
    <div class="container-fluid px-0">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Editar elemento de Navegación CMS</h1>
                <p class="text-secondary mb-0">{{ $item->label }}</p>
            </div>
            <a href="{{ route('admin.cms-navigation.index') }}" class="btn btn-outline-secondary">Volver</a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.cms-navigation.update', $item) }}">
                    @method('PUT')
                    @include('admin.cms-navigation._form')
                </form>
            </div>
        </div>
    </div>
@endsection
