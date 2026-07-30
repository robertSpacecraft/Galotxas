@extends('admin.layout')

@section('content')
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Editar actividad</h1>
                <p class="text-secondary mb-0">
                    {{ $activity->name }} · {{ $activity->status->label() }}
                </p>
            </div>

            <a
                href="{{ route('admin.school.educational-activities.show', $activity) }}"
                class="btn btn-outline-secondary"
            >
                Volver
            </a>
        </div>

        @if ($activity->status !== \App\Enums\EducationalActivityStatus::PLANNED)
            <div class="alert alert-info">
                Esta actividad es histórica. Puedes corregir sus datos, pero no
                reactivar ni cambiar su estado desde este formulario.
            </div>
        @endif

        <div class="card page-card">
            <div class="card-body">
                <form
                    method="POST"
                    action="{{ route('admin.school.educational-activities.update', $activity) }}"
                >
                    @method('PUT')
                    @include('admin.school.educational-activities._form')
                </form>
            </div>
        </div>
    </div>
@endsection
