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
        <label for="name" class="form-label">Nombre público</label>
        <input
            id="name"
            type="text"
            name="name"
            class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $sponsor->name) }}"
            maxlength="255"
            required
        >
        <div class="form-text">También se utiliza como texto alternativo accesible del logo.</div>
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
            class="form-control @error('sort_order') is-invalid @enderror"
            value="{{ old('sort_order', $sponsor->sort_order ?? 0) }}"
            min="0"
            max="65535"
            required
        >
        @error('sort_order')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-7">
        <label for="logo" class="form-label">
            Logo {{ $sponsor->exists ? 'nuevo (opcional)' : '' }}
        </label>
        <input
            id="logo"
            type="file"
            name="logo"
            class="form-control @error('logo') is-invalid @enderror"
            accept="image/jpeg,image/png,image/webp"
            @required(! $sponsor->exists)
        >
        <div class="form-text">
            JPEG, PNG o WebP. Máximo 8 MB y 6000 × 6000 px; se normaliza hasta 1200 × 600 px.
        </div>
        @error('logo')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    @if ($sponsor->exists)
        <div class="col-md-5">
            <span class="form-label d-block">Logo actual</span>
            <img
                src="{{ route('admin.sponsors.logo', $sponsor) }}"
                alt="{{ $sponsor->name }}"
                width="{{ $sponsor->logo_width }}"
                height="{{ $sponsor->logo_height }}"
                class="img-thumbnail"
                style="max-width: 220px; max-height: 110px; object-fit: contain;"
            >
        </div>
    @endif

    <div class="col-12">
        <label for="website_url" class="form-label">Web externa (opcional)</label>
        <input
            id="website_url"
            type="url"
            name="website_url"
            class="form-control @error('website_url') is-invalid @enderror"
            value="{{ old('website_url', $sponsor->website_url) }}"
            maxlength="2048"
            placeholder="https://example.com"
        >
        <div class="form-text">Sólo HTTPS; el enlace público se abre en una pestaña nueva.</div>
        @error('website_url')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="starts_at" class="form-label">Inicio de visibilidad (opcional)</label>
        <input
            id="starts_at"
            type="datetime-local"
            name="starts_at"
            class="form-control @error('starts_at') is-invalid @enderror"
            value="{{ old('starts_at', $sponsor->starts_at?->format('Y-m-d\TH:i')) }}"
        >
        @error('starts_at')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="ends_at" class="form-label">Fin de visibilidad (opcional)</label>
        <input
            id="ends_at"
            type="datetime-local"
            name="ends_at"
            class="form-control @error('ends_at') is-invalid @enderror"
            value="{{ old('ends_at', $sponsor->ends_at?->format('Y-m-d\TH:i')) }}"
        >
        <div class="form-text">El inicio es inclusivo y el fin exclusivo. Zona horaria: {{ config('app.timezone') }}.</div>
        @error('ends_at')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <input type="hidden" name="is_active" value="0">
        <div class="form-check form-switch">
            <input
                id="is_active"
                type="checkbox"
                name="is_active"
                value="1"
                class="form-check-input @error('is_active') is-invalid @enderror"
                @checked((bool) old('is_active', $sponsor->is_active ?? false))
            >
            <label for="is_active" class="form-check-label">Activo</label>
            <div class="form-text">
                Para mostrarse públicamente también debe encontrarse dentro de la ventana temporal.
            </div>
        </div>
        @error('is_active')
        <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 d-flex gap-2 pt-2">
        <button type="submit" class="btn btn-primary">Guardar</button>
        <a href="{{ route('admin.sponsors.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</div>
