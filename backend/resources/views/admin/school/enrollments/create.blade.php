@extends('admin.layout')

@section('content')
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Alta manual de inscripción</h1>
                <p class="text-secondary mb-0">
                    Registra una solicitud pendiente sin crear cuentas ni perfiles deportivos.
                </p>
            </div>

            <a href="{{ route('admin.school.enrollments.index') }}" class="btn btn-outline-secondary">
                Volver
            </a>
        </div>

        @if ($programs->isEmpty())
            <div class="alert alert-warning">
                Debes crear un programa de Escuela antes de registrar inscripciones.
                <a href="{{ route('admin.school.programs.create') }}">Crear programa</a>
            </div>
        @else
            <div class="card page-card">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.school.enrollments.store') }}">
                        @include('admin.school.enrollments._form')
                    </form>
                </div>
            </div>
        @endif
    </div>
@endsection
