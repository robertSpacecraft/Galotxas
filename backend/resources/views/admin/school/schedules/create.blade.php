@extends('admin.layout')

@section('content')
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Crear horario</h1>
                <p class="text-secondary mb-0">
                    Los horarios nuevos permanecen inactivos por defecto.
                </p>
            </div>

            <a href="{{ route('admin.school.schedules.index') }}" class="btn btn-outline-secondary">
                Volver
            </a>
        </div>

        @if ($levels->isEmpty() || $locations->isEmpty())
            <div class="alert alert-warning">
                Debes disponer de al menos un nivel y una ubicación escolar antes de crear horarios.
            </div>
        @else
            <div class="card page-card">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.school.schedules.store') }}">
                        @include('admin.school.schedules._form')
                    </form>
                </div>
            </div>
        @endif
    </div>
@endsection
