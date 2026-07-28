@extends('admin.layout')

@section('content')
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Editar centro educativo</h1>
                <p class="text-secondary mb-0">{{ $center->name }}</p>
            </div>

            <a
                href="{{ route('admin.school.educational-centers.show', $center) }}"
                class="btn btn-outline-secondary"
            >
                Volver
            </a>
        </div>

        <div class="card page-card">
            <div class="card-body">
                <form
                    method="POST"
                    action="{{ route('admin.school.educational-centers.update', $center) }}"
                >
                    @method('PUT')
                    @include('admin.school.educational-centers._form')
                </form>
            </div>
        </div>
    </div>
@endsection
