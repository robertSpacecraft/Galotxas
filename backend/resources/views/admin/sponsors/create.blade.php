@extends('admin.layout')

@section('content')
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Crear colaborador</h1>
                <p class="text-secondary mb-0">Los colaboradores nuevos están inactivos por defecto.</p>
            </div>
            <a href="{{ route('admin.sponsors.index') }}" class="btn btn-outline-secondary">Volver</a>
        </div>

        <div class="card page-card">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.sponsors.store') }}" enctype="multipart/form-data">
                    @include('admin.sponsors._form')
                </form>
            </div>
        </div>
    </div>
@endsection
