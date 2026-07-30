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

@if (isset($programs))
    <section class="mb-4" aria-labelledby="enrollment-assignment-heading">
        <h2 id="enrollment-assignment-heading" class="h5">Programa y nivel solicitado</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <label for="school_program_id" class="form-label">Programa</label>
                <select
                    id="school_program_id"
                    name="school_program_id"
                    required
                    class="form-select @error('school_program_id') is-invalid @enderror"
                >
                    <option value="">Selecciona un programa</option>
                    @foreach ($programs as $program)
                        <option
                            value="{{ $program->id }}"
                            @selected((string) old('school_program_id', $enrollment->school_program_id) === (string) $program->id)
                        >
                            {{ $program->name }}
                        </option>
                    @endforeach
                </select>
                @error('school_program_id')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label for="school_level_id" class="form-label">Nivel solicitado (opcional)</label>
                <select
                    id="school_level_id"
                    name="school_level_id"
                    class="form-select @error('school_level_id') is-invalid @enderror"
                >
                    <option value="">Sin nivel solicitado</option>
                    @foreach ($levels as $level)
                        <option
                            value="{{ $level->id }}"
                            @selected((string) old('school_level_id', $enrollment->school_level_id) === (string) $level->id)
                        >
                            {{ $level->program->name }} — {{ $level->name }}
                        </option>
                    @endforeach
                </select>
                @error('school_level_id')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">
                    Sólo se muestran niveles activos. La inscripción se crea pendiente y debe aprobarse después.
                </div>
            </div>
        </div>
    </section>
@endif

<section class="mb-4" aria-labelledby="participant-heading">
    <h2 id="participant-heading" class="h5">Datos del participante</h2>
    <div class="row g-3">
        <div class="col-md-8">
            <label for="participant_name" class="form-label">Nombre completo</label>
            <input
                id="participant_name"
                type="text"
                name="participant_name"
                value="{{ old('participant_name', $enrollment->participant_name) }}"
                maxlength="255"
                required
                class="form-control @error('participant_name') is-invalid @enderror"
            >
            @error('participant_name')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label for="participant_birth_date" class="form-label">Fecha de nacimiento</label>
            <input
                id="participant_birth_date"
                type="date"
                name="participant_birth_date"
                value="{{ old('participant_birth_date', $enrollment->participant_birth_date?->format('Y-m-d')) }}"
                max="{{ now()->toDateString() }}"
                required
                class="form-control @error('participant_birth_date') is-invalid @enderror"
            >
            @error('participant_birth_date')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">
                La minoría de edad se calcula en la fecha de solicitud.
            </div>
        </div>
    </div>
</section>

<section class="mb-4" aria-labelledby="contact-heading">
    <h2 id="contact-heading" class="h5">Contacto</h2>
    <div class="row g-3">
        <div class="col-md-6">
            <label for="contact_phone" class="form-label">Teléfono</label>
            <input
                id="contact_phone"
                type="text"
                name="contact_phone"
                value="{{ old('contact_phone', $enrollment->contact_phone) }}"
                maxlength="50"
                required
                class="form-control @error('contact_phone') is-invalid @enderror"
            >
            @error('contact_phone')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label for="contact_email" class="form-label">Correo electrónico</label>
            <input
                id="contact_email"
                type="email"
                name="contact_email"
                value="{{ old('contact_email', $enrollment->contact_email) }}"
                maxlength="255"
                required
                class="form-control @error('contact_email') is-invalid @enderror"
            >
            @error('contact_email')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</section>

<section class="mb-4" aria-labelledby="guardian-heading">
    <h2 id="guardian-heading" class="h5">Representante del menor</h2>
    <p class="text-secondary">
        Ambos campos son obligatorios si el participante era menor al solicitar.
        Para adultos se eliminan aunque se envíen.
    </p>
    <div class="row g-3">
        <div class="col-md-7">
            <label for="guardian_name" class="form-label">Nombre del representante</label>
            <input
                id="guardian_name"
                type="text"
                name="guardian_name"
                value="{{ old('guardian_name', $enrollment->guardian_name) }}"
                maxlength="255"
                class="form-control @error('guardian_name') is-invalid @enderror"
            >
            @error('guardian_name')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-5">
            <label for="guardian_relationship" class="form-label">Relación</label>
            <input
                id="guardian_relationship"
                type="text"
                name="guardian_relationship"
                value="{{ old('guardian_relationship', $enrollment->guardian_relationship) }}"
                maxlength="100"
                class="form-control @error('guardian_relationship') is-invalid @enderror"
            >
            @error('guardian_relationship')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</section>

<section class="mb-4" aria-labelledby="notes-heading">
    <h2 id="notes-heading" class="h5">Uso administrativo</h2>
    <label for="admin_notes" class="form-label">Notas internas</label>
    <textarea
        id="admin_notes"
        name="admin_notes"
        rows="4"
        maxlength="10000"
        class="form-control @error('admin_notes') is-invalid @enderror"
    >{{ old('admin_notes', $enrollment->admin_notes) }}</textarea>
    @error('admin_notes')
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <div class="form-text">Nunca se exponen mediante la API pública.</div>
</section>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Guardar</button>
    <a
        href="{{ $enrollment->exists ? route('admin.school.enrollments.show', $enrollment) : route('admin.school.enrollments.index') }}"
        class="btn btn-outline-secondary"
    >
        Cancelar
    </a>
</div>
