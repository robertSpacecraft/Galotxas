@extends('admin.layout')

@section('content')
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Nueva temporada</h1>
                <p class="text-secondary mb-0">Alta de una temporada de competición</p>
            </div>

            <a href="{{ route('admin.seasons.index') }}" class="btn btn-outline-secondary">Volver</a>
        </div>

        <div class="card page-card">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.seasons.store') }}">
                    @include('admin.seasons._form')
                </form>
            </div>
        </div>
    </div>
@endsection
