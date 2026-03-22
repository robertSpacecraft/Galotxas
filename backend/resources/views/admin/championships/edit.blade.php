@extends('admin.layout')

@section('content')

    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Editar campeonato</h1>
                <p class="text-secondary mb-0">Temporada: {{ $championship->season->name }}</p>
            </div>

            <a href="{{ route('admin.seasons.championships', $championship->season) }}"
               class="btn btn-outline-secondary">
                Volver al listado
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
                <form method="POST" action="{{ route('admin.championships.update', $championship) }}" class="row g-3">
                    @csrf
                    @method('PUT')

                    <div class="col-md-6">
                        <label for="name" class="form-label">Nombre</label>
                        <input
                            id="name"
                            type="text"
                            name="name"
                            class="form-control"
                            value="{{ old('name', $championship->name) }}"
                            required
                        >
                    </div>

                    <div class="col-md-6">
                        <label for="type" class="form-label">Tipo</label>
                        <select id="type" name="type" class="form-select" required>
                            <option value="singles" {{ old('type', $championship->type?->value ?? $championship->type) === 'singles' ? 'selected' : '' }}>
                                Singles
                            </option>
                            <option value="doubles" {{ old('type', $championship->type?->value ?? $championship->type) === 'doubles' ? 'selected' : '' }}>
                                Doubles
                            </option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label for="description" class="form-label">Descripción</label>
                        <textarea
                            id="description"
                            name="description"
                            class="form-control"
                            rows="3"
                        >{{ old('description', $championship->description) }}</textarea>
                    </div>

                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Inicio campeonato</label>
                        <input
                            id="start_date"
                            type="date"
                            name="start_date"
                            class="form-control"
                            value="{{ old('start_date', $championship->start_date?->format('Y-m-d')) }}"
                        >
                    </div>

                    <div class="col-md-3">
                        <label for="end_date" class="form-label">Fin campeonato</label>
                        <input
                            id="end_date"
                            type="date"
                            name="end_date"
                            class="form-control"
                            value="{{ old('end_date', $championship->end_date?->format('Y-m-d')) }}"
                        >
                    </div>

                    <div class="col-md-3">
                        <label for="status" class="form-label">Estado campeonato</label>
                        <select id="status" name="status" class="form-select" required>
                            <option value="pending" {{ old('status', $championship->status?->value ?? $championship->status) === 'pending' ? 'selected' : '' }}>
                                pending
                            </option>
                            <option value="active" {{ old('status', $championship->status?->value ?? $championship->status) === 'active' ? 'selected' : '' }}>
                                active
                            </option>
                            <option value="finished" {{ old('status', $championship->status?->value ?? $championship->status) === 'finished' ? 'selected' : '' }}>
                                finished
                            </option>
                            <option value="cancelled" {{ old('status', $championship->status?->value ?? $championship->status) === 'cancelled' ? 'selected' : '' }}>
                                cancelled
                            </option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="registration_status" class="form-label">Estado inscripciones</label>
                        <select id="registration_status" name="registration_status" class="form-select" required>
                            <option value="closed" {{ old('registration_status', $championship->registration_status?->value ?? $championship->registration_status) === 'closed' ? 'selected' : '' }}>
                                Cerradas
                            </option>
                            <option value="open" {{ old('registration_status', $championship->registration_status?->value ?? $championship->registration_status) === 'open' ? 'selected' : '' }}>
                                Abiertas
                            </option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="registration_starts_at" class="form-label">Inicio inscripciones</label>
                        <input
                            id="registration_starts_at"
                            type="datetime-local"
                            name="registration_starts_at"
                            class="form-control"
                            value="{{ old('registration_starts_at', $championship->registration_starts_at?->format('Y-m-d\TH:i')) }}"
                        >
                    </div>

                    <div class="col-md-6">
                        <label for="registration_ends_at" class="form-label">Fin inscripciones</label>
                        <input
                            id="registration_ends_at"
                            type="datetime-local"
                            name="registration_ends_at"
                            class="form-control"
                            value="{{ old('registration_ends_at', $championship->registration_ends_at?->format('Y-m-d\TH:i')) }}"
                        >
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection
