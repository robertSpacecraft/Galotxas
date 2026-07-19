@csrf

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

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Nombre</label>
        <input
            id="name"
            type="text"
            name="name"
            class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $season->name) }}"
            maxlength="255"
            required
        >
        @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="status" class="form-label">Estado</label>
        <select
            id="status"
            name="status"
            class="form-select @error('status') is-invalid @enderror"
            required
        >
            @foreach ($statusOptions as $status)
                <option
                    value="{{ $status->value }}"
                    @selected(old('status', $season->status?->value ?? $defaultStatus) === $status->value)
                >{{ ucfirst($status->value) }}</option>
            @endforeach
        </select>
        @error('status')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="start_date" class="form-label">Fecha de inicio</label>
        <input
            id="start_date"
            type="date"
            name="start_date"
            class="form-control @error('start_date') is-invalid @enderror"
            value="{{ old('start_date', $season->start_date?->format('Y-m-d')) }}"
        >
        @error('start_date')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="end_date" class="form-label">Fecha de fin</label>
        <input
            id="end_date"
            type="date"
            name="end_date"
            class="form-control @error('end_date') is-invalid @enderror"
            value="{{ old('end_date', $season->end_date?->format('Y-m-d')) }}"
        >
        @error('end_date')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Si se indican ambas fechas, la fecha de fin no puede ser anterior al inicio.</div>
    </div>

    <div class="col-12 d-flex gap-2 pt-2">
        <button type="submit" class="btn btn-primary">Guardar</button>
        <a href="{{ route('admin.seasons.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</div>
