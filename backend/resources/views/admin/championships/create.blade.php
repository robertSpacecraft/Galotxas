@extends('admin.layout')

@section('content')

    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Crear campeonato</h1>
                <p class="text-secondary mb-0">Nuevo campeonato para la temporada {{ $season->name }}</p>
            </div>

            <a href="{{ route('admin.seasons.championships', $season) }}" class="btn btn-outline-secondary">
                Volver
            </a>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Se han encontrado errores:</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card page-card">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.championships.store', $season) }}" class="row g-3">
                    @csrf

                    <div class="col-md-6">
                        <label for="name" class="form-label">Nombre</label>
                        <input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}" required>
                    </div>

                    <div class="col-md-6">
                        <label for="type" class="form-label">Tipo</label>
                        <select name="type" id="type" class="form-select" required>
                            <option value="">Selecciona tipo</option>
                            <option value="singles" {{ old('type') === 'singles' ? 'selected' : '' }}>Singles</option>
                            <option value="doubles" {{ old('type') === 'doubles' ? 'selected' : '' }}>Doubles</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label for="description" class="form-label">Descripción</label>
                        <textarea name="description" id="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                    </div>

                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Inicio campeonato</label>
                        <input type="date" name="start_date" id="start_date" class="form-control" value="{{ old('start_date') }}">
                    </div>

                    <div class="col-md-3">
                        <label for="end_date" class="form-label">Fin campeonato</label>
                        <input type="date" name="end_date" id="end_date" class="form-control" value="{{ old('end_date') }}">
                    </div>

                    <div class="col-md-3">
                        <label for="status" class="form-label">Estado campeonato</label>
                        <select name="status" id="status" class="form-select" required>
                            <option value="pending" {{ old('status', 'pending') === 'pending' ? 'selected' : '' }}>pending</option>
                            <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>active</option>
                            <option value="finished" {{ old('status') === 'finished' ? 'selected' : '' }}>finished</option>
                            <option value="cancelled" {{ old('status') === 'cancelled' ? 'selected' : '' }}>cancelled</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="registration_status" class="form-label">Estado inscripciones</label>
                        <select name="registration_status" id="registration_status" class="form-select" required>
                            <option value="closed" {{ old('registration_status', 'closed') === 'closed' ? 'selected' : '' }}>Cerradas</option>
                            <option value="open" {{ old('registration_status') === 'open' ? 'selected' : '' }}>Abiertas</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="registration_starts_at" class="form-label">Inicio inscripciones</label>
                        <input
                            type="datetime-local"
                            name="registration_starts_at"
                            id="registration_starts_at"
                            class="form-control"
                            value="{{ old('registration_starts_at') }}"
                        >
                    </div>

                    <div class="col-md-3">
                        <label for="registration_ends_at" class="form-label">Fin inscripciones</label>
                        <input
                            type="datetime-local"
                            name="registration_ends_at"
                            id="registration_ends_at"
                            class="form-control"
                            value="{{ old('registration_ends_at') }}"
                        >
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Crear campeonato</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection
