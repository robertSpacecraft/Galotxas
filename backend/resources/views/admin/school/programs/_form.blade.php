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
    <div class="col-md-8">
        <label for="name" class="form-label">Nombre</label>
        <input
            id="name"
            type="text"
            name="name"
            value="{{ old('name', $program->name) }}"
            maxlength="255"
            required
            class="form-control @error('name') is-invalid @enderror"
        >
        @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="sort_order" class="form-label">Orden</label>
        <input
            id="sort_order"
            type="number"
            name="sort_order"
            value="{{ old('sort_order', $program->sort_order ?? 0) }}"
            min="0"
            max="65535"
            required
            class="form-control @error('sort_order') is-invalid @enderror"
        >
        @error('sort_order')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <label for="public_description" class="form-label">Presentación pública</label>
        <textarea
            id="public_description"
            name="public_description"
            rows="4"
            maxlength="5000"
            class="form-control @error('public_description') is-invalid @enderror"
            aria-describedby="public_description_help"
        >{{ old('public_description', $program->public_description) }}</textarea>
        @error('public_description')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div id="public_description_help" class="form-text">
            Explica qué es la Escuela sin inventar metodología ni datos pendientes.
        </div>
    </div>

    <div class="col-12">
        <label for="enrollment_information" class="form-label">Proceso público de inscripción</label>
        <textarea
            id="enrollment_information"
            name="enrollment_information"
            rows="4"
            maxlength="5000"
            class="form-control @error('enrollment_information') is-invalid @enderror"
            aria-describedby="enrollment_information_help"
        >{{ old('enrollment_information', $program->enrollment_information) }}</textarea>
        @error('enrollment_information')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div id="enrollment_information_help" class="form-text">
            Describe el trámite real. No incluyas horarios o niveles que ya se gestionan de forma estructurada.
        </div>
    </div>

    <div class="col-12">
        <label for="default_school_location_id" class="form-label">Ubicación habitual</label>
        <select
            id="default_school_location_id"
            name="default_school_location_id"
            class="form-select @error('default_school_location_id') is-invalid @enderror"
            aria-describedby="default_location_help"
        >
            <option value="">Sin ubicación habitual</option>
            @foreach ($locations as $location)
                <option
                    value="{{ $location->id }}"
                    @selected((string) old('default_school_location_id', $program->default_school_location_id) === (string) $location->id)
                >
                    {{ $location->name }} — {{ $location->locality }}
                    ({{ $location->is_active ? 'activa' : 'inactiva' }})
                </option>
            @endforeach
        </select>
        @error('default_school_location_id')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div id="default_location_help" class="form-text">
            Si se selecciona una ubicación al publicar, debe estar activa.
        </div>
    </div>

    <div class="col-md-6">
        <label for="contact_phone" class="form-label">Teléfono operativo privado (opcional)</label>
        <input
            id="contact_phone"
            type="text"
            name="contact_phone"
            value="{{ old('contact_phone', $program->contact_phone) }}"
            maxlength="50"
            class="form-control @error('contact_phone') is-invalid @enderror"
        >
        @error('contact_phone')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="contact_email" class="form-label">Correo operativo privado</label>
        <input
            id="contact_email"
            type="email"
            name="contact_email"
            value="{{ old('contact_email', $program->contact_email) }}"
            maxlength="255"
            class="form-control @error('contact_email') is-invalid @enderror"
        >
        @error('contact_email')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Se usa para preparar la gestión; no se expone en la API pública.</div>
    </div>

    <div class="col-md-6">
        <input type="hidden" name="is_public" value="0">
        <div class="form-check">
            <input
                id="is_public"
                type="checkbox"
                name="is_public"
                value="1"
                class="form-check-input @error('is_public') is-invalid @enderror"
                aria-describedby="is_public_help"
                @checked((bool) old('is_public', $program->is_public ?? false))
            >
            <label for="is_public" class="form-check-label">Programa público</label>
            @error('is_public')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div id="is_public_help" class="form-text">
            Sólo puede existir un programa público. Publicarlo no abre las inscripciones.
        </div>
    </div>

    <div class="col-md-6">
        <input type="hidden" name="enrollments_open" value="0">
        <div class="form-check">
            <input
                id="enrollments_open"
                type="checkbox"
                name="enrollments_open"
                value="1"
                class="form-check-input @error('enrollments_open') is-invalid @enderror"
                aria-describedby="enrollments_open_help"
                @checked((bool) old('enrollments_open', $program->enrollments_open ?? false))
            >
            <label for="enrollments_open" class="form-check-label">
                Inscripciones declaradas abiertas
            </label>
            @error('enrollments_open')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div id="enrollments_open_help" class="form-text">
            La apertura efectiva exige programa público, contenido, ubicación, correo
            operativo, un nivel con horario activo, aviso vigente y el flag seguro del entorno.
            El entorno permanece cerrado por defecto.
        </div>
    </div>

    <div class="col-12 d-flex gap-2 pt-2">
        <button type="submit" class="btn btn-primary">Guardar</button>
        <a href="{{ route('admin.school.programs.index') }}" class="btn btn-outline-secondary">
            Cancelar
        </a>
    </div>
</div>
