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
            value="{{ old('name', $center->name) }}"
            maxlength="255"
            required
            class="form-control @error('name') is-invalid @enderror"
        >
        @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="locality" class="form-label">Localidad</label>
        <input
            id="locality"
            type="text"
            name="locality"
            value="{{ old('locality', $center->locality) }}"
            maxlength="255"
            required
            class="form-control @error('locality') is-invalid @enderror"
        >
        @error('locality')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="contact_name" class="form-label">Persona de contacto</label>
        <input
            id="contact_name"
            type="text"
            name="contact_name"
            value="{{ old('contact_name', $center->contact_name) }}"
            maxlength="255"
            class="form-control @error('contact_name') is-invalid @enderror"
        >
        @error('contact_name')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="contact_phone" class="form-label">Teléfono</label>
        <input
            id="contact_phone"
            type="text"
            name="contact_phone"
            value="{{ old('contact_phone', $center->contact_phone) }}"
            maxlength="255"
            class="form-control @error('contact_phone') is-invalid @enderror"
        >
        @error('contact_phone')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="contact_email" class="form-label">Correo</label>
        <input
            id="contact_email"
            type="email"
            name="contact_email"
            value="{{ old('contact_email', $center->contact_email) }}"
            maxlength="255"
            class="form-control @error('contact_email') is-invalid @enderror"
        >
        @error('contact_email')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
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
        >{{ old('admin_notes', $center->admin_notes) }}</textarea>
        @error('admin_notes')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div id="admin_notes_help" class="form-text">
            Información privada, visible sólo en administración.
        </div>
    </div>

    <div class="col-12">
        <input type="hidden" name="is_active" value="0">
        <div class="form-check">
            <input
                id="is_active"
                type="checkbox"
                name="is_active"
                value="1"
                class="form-check-input @error('is_active') is-invalid @enderror"
                @checked((bool) old('is_active', $center->is_active ?? false))
            >
            <label for="is_active" class="form-check-label">Centro activo</label>
            @error('is_active')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-text">
            Sólo los centros activos pueden recibir actividades nuevas.
        </div>
    </div>

    <div class="col-12 d-flex gap-2 pt-2">
        <button type="submit" class="btn btn-primary">Guardar</button>
        <a
            href="{{ $center->exists
                ? route('admin.school.educational-centers.show', $center)
                : route('admin.school.educational-centers.index') }}"
            class="btn btn-outline-secondary"
        >
            Cancelar
        </a>
    </div>
</div>
