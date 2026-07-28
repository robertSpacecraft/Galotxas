@extends('admin.layout')

@section('content')
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Crear actividad con centro</h1>
                <p class="text-secondary mb-0">
                    Toda actividad nueva comienza como planificada.
                </p>
            </div>

            <a
                href="{{ route('admin.school.educational-activities.index') }}"
                class="btn btn-outline-secondary"
            >
                Volver
            </a>
        </div>

        @if ($centers->isEmpty())
            <div class="alert alert-warning">
                Debes activar al menos un centro educativo antes de crear actividades.
                <a href="{{ route('admin.school.educational-centers.index') }}">
                    Gestionar centros
                </a>
            </div>
        @else
            <div class="card page-card">
                <div class="card-body">
                    <form
                        method="POST"
                        action="{{ route('admin.school.educational-activities.store') }}"
                    >
                        @include('admin.school.educational-activities._form')
                    </form>
                </div>
            </div>
        @endif
    </div>
@endsection
