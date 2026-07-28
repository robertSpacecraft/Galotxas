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
        <label for="school_level_id" class="form-label">Nivel</label>
        <select
            id="school_level_id"
            name="school_level_id"
            required
            class="form-select @error('school_level_id') is-invalid @enderror"
        >
            <option value="">Selecciona un nivel</option>
            @foreach ($levels as $level)
                <option
                    value="{{ $level->id }}"
                    @selected((string) old('school_level_id', $schedule->school_level_id) === (string) $level->id)
                >
                    {{ $level->program->name }} — {{ $level->name }}
                    ({{ $level->is_active ? 'activo' : 'inactivo' }})
                </option>
            @endforeach
        </select>
        @error('school_level_id')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="school_location_id" class="form-label">Ubicación</label>
        <select
            id="school_location_id"
            name="school_location_id"
            required
            class="form-select @error('school_location_id') is-invalid @enderror"
        >
            <option value="">Selecciona una ubicación</option>
            @foreach ($locations as $location)
                <option
                    value="{{ $location->id }}"
                    @selected((string) old('school_location_id', $schedule->school_location_id) === (string) $location->id)
                >
                    {{ $location->name }} — {{ $location->locality }}
                    ({{ $location->is_active ? 'activa' : 'inactiva' }})
                </option>
            @endforeach
        </select>
        @error('school_location_id')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="day_of_week" class="form-label">Día de la semana</label>
        <select
            id="day_of_week"
            name="day_of_week"
            required
            class="form-select @error('day_of_week') is-invalid @enderror"
        >
            <option value="">Selecciona un día</option>
            @foreach ($dayOptions as $day)
                <option
                    value="{{ $day->value }}"
                    @selected((string) old('day_of_week', $schedule->day_of_week?->value) === (string) $day->value)
                >
                    {{ $day->value }} — {{ $day->label() }}
                </option>
            @endforeach
        </select>
        @error('day_of_week')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="starts_at" class="form-label">Hora de inicio</label>
        <input
            id="starts_at"
            type="time"
            name="starts_at"
            value="{{ old('starts_at', $schedule->exists ? $schedule->startsAtLabel() : '') }}"
            required
            class="form-control @error('starts_at') is-invalid @enderror"
        >
        @error('starts_at')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="ends_at" class="form-label">Hora de finalización</label>
        <input
            id="ends_at"
            type="time"
            name="ends_at"
            value="{{ old('ends_at', $schedule->exists ? $schedule->endsAtLabel() : '') }}"
            required
            class="form-control @error('ends_at') is-invalid @enderror"
        >
        @error('ends_at')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="sort_order" class="form-label">Orden</label>
        <input
            id="sort_order"
            type="number"
            name="sort_order"
            value="{{ old('sort_order', $schedule->sort_order ?? 0) }}"
            min="0"
            max="65535"
            required
            class="form-control @error('sort_order') is-invalid @enderror"
        >
        @error('sort_order')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 d-flex align-items-end">
        <div>
            <input type="hidden" name="is_active" value="0">
            <div class="form-check">
                <input
                    id="is_active"
                    type="checkbox"
                    name="is_active"
                    value="1"
                    class="form-check-input @error('is_active') is-invalid @enderror"
                    @checked((bool) old('is_active', $schedule->is_active ?? false))
                >
                <label for="is_active" class="form-check-label">Horario activo</label>
                @error('is_active')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-text">
                Para activarlo, el nivel y la ubicación deben estar activos. La visibilidad
                exige además nivel y programa públicos.
            </div>
        </div>
    </div>

    <div class="col-12 d-flex gap-2 pt-2">
        <button type="submit" class="btn btn-primary">Guardar</button>
        <a href="{{ route('admin.school.schedules.index') }}" class="btn btn-outline-secondary">
            Cancelar
        </a>
    </div>
</div>
