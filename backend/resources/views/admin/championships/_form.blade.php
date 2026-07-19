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
        <label for="season_id" class="form-label">Temporada</label>
        <select
            id="season_id"
            name="season_id"
            class="form-select @error('season_id') is-invalid @enderror"
            required
        >
            @foreach ($seasons as $seasonOption)
                <option
                    value="{{ $seasonOption->id }}"
                    @selected((string) old('season_id', $championship->season_id) === (string) $seasonOption->id)
                >{{ $seasonOption->name }} — {{ $seasonOption->is_public ? 'Pública' : 'Privada' }}</option>
            @endforeach
        </select>
        @error('season_id')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">
            Un campeonato sólo puede ser público cuando la temporada seleccionada también es pública.
        </div>
    </div>

    <div class="col-md-6">
        <label for="name" class="form-label">Nombre</label>
        <input
            id="name"
            type="text"
            name="name"
            class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $championship->name) }}"
            maxlength="255"
            required
        >
        @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="type" class="form-label">Tipo</label>
        <select
            id="type"
            name="type"
            class="form-select @error('type') is-invalid @enderror"
            required
        >
            <option value="">Selecciona tipo</option>
            @foreach ($typeOptions as $type)
                <option
                    value="{{ $type->value }}"
                    @selected(old('type', $championship->type?->value) === $type->value)
                >{{ ucfirst($type->value) }}</option>
            @endforeach
        </select>
        @error('type')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="status" class="form-label">Estado campeonato</label>
        <select
            id="status"
            name="status"
            class="form-select @error('status') is-invalid @enderror"
            required
        >
            @foreach ($statusOptions as $status)
                <option
                    value="{{ $status->value }}"
                    @selected(old('status', $championship->status ?? $defaultStatus) === $status->value)
                >{{ $status->label() }}</option>
            @endforeach
        </select>
        @error('status')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="registration_status" class="form-label">Estado inscripciones</label>
        <select
            id="registration_status"
            name="registration_status"
            class="form-select @error('registration_status') is-invalid @enderror"
            required
        >
            @foreach ($registrationStatusOptions as $registrationStatus)
                <option
                    value="{{ $registrationStatus->value }}"
                    @selected(old(
                        'registration_status',
                        $championship->registration_status?->value ?? $defaultRegistrationStatus
                    ) === $registrationStatus->value)
                >{{ $registrationStatus->label() }}</option>
            @endforeach
        </select>
        @error('registration_status')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <input type="hidden" name="is_public" value="0">
        <div class="form-check">
            <input
                id="is_public"
                type="checkbox"
                name="is_public"
                value="1"
                class="form-check-input @error('is_public') is-invalid @enderror"
                aria-describedby="is_public_help"
                @checked((bool) old('is_public', $championship->is_public ?? false))
            >
            <label for="is_public" class="form-check-label">Visible públicamente</label>
            @error('is_public')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div id="is_public_help" class="form-text">
            La visibilidad es independiente del estado operativo y requiere una temporada pública.
        </div>
    </div>

    <div class="col-12">
        <label for="description" class="form-label">Descripción</label>
        <textarea
            id="description"
            name="description"
            class="form-control @error('description') is-invalid @enderror"
            rows="4"
            maxlength="5000"
        >{{ old('description', $championship->description) }}</textarea>
        @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="start_date" class="form-label">Inicio campeonato</label>
        <input
            id="start_date"
            type="date"
            name="start_date"
            class="form-control @error('start_date') is-invalid @enderror"
            value="{{ old('start_date', $championship->start_date?->format('Y-m-d')) }}"
        >
        @error('start_date')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="end_date" class="form-label">Fin campeonato</label>
        <input
            id="end_date"
            type="date"
            name="end_date"
            class="form-control @error('end_date') is-invalid @enderror"
            value="{{ old('end_date', $championship->end_date?->format('Y-m-d')) }}"
        >
        @error('end_date')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="registration_starts_at" class="form-label">Inicio inscripciones</label>
        <input
            id="registration_starts_at"
            type="datetime-local"
            name="registration_starts_at"
            class="form-control @error('registration_starts_at') is-invalid @enderror"
            value="{{ old(
                'registration_starts_at',
                $championship->registration_starts_at?->format('Y-m-d\TH:i')
            ) }}"
        >
        @error('registration_starts_at')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="registration_ends_at" class="form-label">Fin inscripciones</label>
        <input
            id="registration_ends_at"
            type="datetime-local"
            name="registration_ends_at"
            class="form-control @error('registration_ends_at') is-invalid @enderror"
            value="{{ old(
                'registration_ends_at',
                $championship->registration_ends_at?->format('Y-m-d\TH:i')
            ) }}"
        >
        @error('registration_ends_at')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Cada fecha final debe ser igual o posterior a su correspondiente fecha inicial.</div>
    </div>

    <div class="col-12 d-flex gap-2 pt-2">
        <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
        <a href="{{ route('admin.seasons.championships', $backSeason) }}" class="btn btn-outline-secondary">
            Cancelar
        </a>
    </div>
</div>
