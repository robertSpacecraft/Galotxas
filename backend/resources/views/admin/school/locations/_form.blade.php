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
            value="{{ old('name', $location->name) }}"
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
            value="{{ old('locality', $location->locality) }}"
            maxlength="255"
            required
            class="form-control @error('locality') is-invalid @enderror"
        >
        @error('locality')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-8">
        <label for="address" class="form-label">Dirección</label>
        <input
            id="address"
            type="text"
            name="address"
            value="{{ old('address', $location->address) }}"
            maxlength="255"
            class="form-control @error('address') is-invalid @enderror"
        >
        @error('address')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="sort_order" class="form-label">Orden</label>
        <input
            id="sort_order"
            type="number"
            name="sort_order"
            value="{{ old('sort_order', $location->sort_order ?? 0) }}"
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
        <label for="admin_notes" class="form-label">Notas administrativas</label>
        <textarea
            id="admin_notes"
            name="admin_notes"
            rows="4"
            maxlength="5000"
            class="form-control @error('admin_notes') is-invalid @enderror"
            aria-describedby="admin_notes_help"
        >{{ old('admin_notes', $location->admin_notes) }}</textarea>
        @error('admin_notes')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div id="admin_notes_help" class="form-text">
            Estas notas son privadas y nunca formarán parte de la lectura pública.
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
                @checked((bool) old('is_active', $location->is_active ?? false))
            >
            <label for="is_active" class="form-check-label">Ubicación activa</label>
            @error('is_active')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-text">
            Una ubicación inactiva no hace efectivos públicamente los horarios que la usan.
        </div>
    </div>

    <div class="col-12 d-flex gap-2 pt-2">
        <button type="submit" class="btn btn-primary">Guardar</button>
        <a href="{{ route('admin.school.locations.index') }}" class="btn btn-outline-secondary">
            Cancelar
        </a>
    </div>
</div>
