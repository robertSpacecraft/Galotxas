@extends('admin.layout')

@section('content')
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Crear centro educativo</h1>
                <p class="text-secondary mb-0">
                    Los centros nuevos permanecen inactivos hasta su revisión.
                </p>
            </div>

            <a
                href="{{ route('admin.school.educational-centers.index') }}"
                class="btn btn-outline-secondary"
            >
                Volver
            </a>
        </div>

        <div class="card page-card">
            <div class="card-body">
                <form
                    method="POST"
                    action="{{ route('admin.school.educational-centers.store') }}"
                >
                    @include('admin.school.educational-centers._form')
                </form>
            </div>
        </div>
    </div>
@endsection
