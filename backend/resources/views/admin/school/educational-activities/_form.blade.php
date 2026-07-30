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
        <label for="educational_center_id" class="form-label">Centro educativo</label>
        <select
            id="educational_center_id"
            name="educational_center_id"
            required
            class="form-select @error('educational_center_id') is-invalid @enderror"
            aria-describedby="educational_center_help"
        >
            <option value="">Selecciona un centro</option>
            @foreach ($centers as $center)
                <option
                    value="{{ $center->id }}"
                    @selected((string) old(
                        'educational_center_id',
                        $selectedCenterId
                    ) === (string) $center->id)
                >
                    {{ $center->locality }} — {{ $center->name }}
                    {{ $center->is_active ? '' : '(inactivo, relación histórica)' }}
                </option>
            @endforeach
        </select>
        @error('educational_center_id')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div id="educational_center_help" class="form-text">
            Un centro inactivo sólo se conserva si ya estaba asignado.
        </div>
    </div>

    <div class="col-md-6">
        <label for="school_location_id" class="form-label">
            Ubicación escolar (opcional)
        </label>
        <select
            id="school_location_id"
            name="school_location_id"
            class="form-select @error('school_location_id') is-invalid @enderror"
            aria-describedby="school_location_help"
        >
            <option value="">Sin ubicación asociada</option>
            @foreach ($locations as $location)
                <option
                    value="{{ $location->id }}"
                    @selected((string) old(
                        'school_location_id',
                        $activity->school_location_id
                    ) === (string) $location->id)
                >
                    {{ $location->name }} — {{ $location->locality }}
                    {{ $location->is_active ? '' : '(inactiva, relación histórica)' }}
                </option>
            @endforeach
        </select>
        @error('school_location_id')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div id="school_location_help" class="form-text">
            Las asignaciones nuevas requieren una ubicación activa.
        </div>
    </div>

    <div class="col-md-8">
        <label for="name" class="form-label">Nombre de la actividad</label>
        <input
            id="name"
            type="text"
            name="name"
            value="{{ old('name', $activity->name) }}"
            maxlength="255"
            required
            class="form-control @error('name') is-invalid @enderror"
        >
        @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Nombre libre para identificar la actuación.</div>
    </div>

    <div class="col-md-4">
        <label for="activity_date" class="form-label">Fecha</label>
        <input
            id="activity_date"
            type="date"
            name="activity_date"
            value="{{ old(
                'activity_date',
                $activity->activity_date?->format('Y-m-d')
            ) }}"
            required
            class="form-control @error('activity_date') is-invalid @enderror"
        >
        @error('activity_date')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="starts_at" class="form-label">Hora de inicio (opcional)</label>
        <input
            id="starts_at"
            type="time"
            name="starts_at"
            value="{{ old('starts_at', $activity->startsAtLabel()) }}"
            class="form-control @error('starts_at') is-invalid @enderror"
        >
        @error('starts_at')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="ends_at" class="form-label">Hora de fin (opcional)</label>
        <input
            id="ends_at"
            type="time"
            name="ends_at"
            value="{{ old('ends_at', $activity->endsAtLabel()) }}"
            class="form-control @error('ends_at') is-invalid @enderror"
        >
        @error('ends_at')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Informa ambas horas o deja ambas vacías.</div>
    </div>

    <div class="col-md-4">
        <label for="expected_students" class="form-label">Alumnado previsto</label>
        <input
            id="expected_students"
            type="number"
            name="expected_students"
            value="{{ old('expected_students', $activity->expected_students) }}"
            min="1"
            max="65535"
            class="form-control @error('expected_students') is-invalid @enderror"
            aria-describedby="expected_students_help"
        >
        @error('expected_students')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div id="expected_students_help" class="form-text">
            Puede quedar vacío mientras está planificada; es obligatorio para completarla.
        </div>
    </div>

    <div class="col-12">
        <label for="admin_notes" class="form-label">Notas administrativas</label>
        <textarea
            id="admin_notes"
            name="admin_notes"
            rows="4"
            maxlength="5000"
            class="form-control @error('admin_notes') is-invalid @enderror"
            aria-describedby="admin_notes_help"
        >{{ old('admin_notes', $activity->admin_notes) }}</textarea>
        @error('admin_notes')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div id="admin_notes_help" class="form-text">
            Información privada, no expuesta mediante rutas públicas o API.
        </div>
    </div>

    <div class="col-12 d-flex gap-2 pt-2">
        <button type="submit" class="btn btn-primary">Guardar</button>
        <a
            href="{{ $activity->exists
                ? route('admin.school.educational-activities.show', $activity)
                : route('admin.school.educational-activities.index') }}"
            class="btn btn-outline-secondary"
        >
            Cancelar
        </a>
    </div>
</div>
