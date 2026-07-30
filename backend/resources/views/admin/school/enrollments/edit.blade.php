@extends('admin.layout')

@section('content')
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Corregir datos de inscripción</h1>
                <p class="text-secondary mb-0">
                    El programa, nivel, cuenta, estado y fechas no se modifican desde este formulario.
                </p>
            </div>

            <a
                href="{{ route('admin.school.enrollments.show', $enrollment) }}"
                class="btn btn-outline-secondary"
            >
                Volver al detalle
            </a>
        </div>

        <div class="alert alert-light border">
            <strong>Programa:</strong> {{ $enrollment->program->name }}
            <span class="mx-2">·</span>
            <strong>Nivel:</strong> {{ $enrollment->level?->name ?? 'Sin asignar' }}
            <span class="mx-2">·</span>
            <strong>Estado:</strong> {{ $enrollment->status->label() }}
        </div>

        <div class="card page-card">
            <div class="card-body">
                <form
                    method="POST"
                    action="{{ route('admin.school.enrollments.update', $enrollment) }}"
                >
                    @method('PUT')
                    @include('admin.school.enrollments._form')
                </form>
            </div>
        </div>
    </div>
@endsection
