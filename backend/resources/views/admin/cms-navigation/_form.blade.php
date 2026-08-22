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

<div class="alert alert-info">
    El slot es <strong>Club</strong>. La página aparecerá siempre después de Quiénes somos,
    Contacto, Federarse y Documentos. La URL se deriva de su slug y no puede editarse aquí.
</div>

<input type="hidden" name="is_active" value="0">

<div class="row g-3">
    <div class="col-12">
        <label for="cms_page_id" class="form-label">Página CMS</label>
        <select
            id="cms_page_id"
            name="cms_page_id"
            class="form-select @error('cms_page_id') is-invalid @enderror"
            required
        >
            <option value="">Selecciona una página elegible</option>
            @foreach ($pages as $page)
                <option
                    value="{{ $page->id }}"
                    {{ (string) old('cms_page_id', $item->cms_page_id) === (string) $page->id ? 'selected' : '' }}
                >
                    {{ $page->title }} — /contenidos/{{ $page->slug }} — {{ $page->publicationState()->label() }}
                </option>
            @endforeach
        </select>
        @error('cms_page_id')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">
            Las páginas estructurales de Club y las ya asignadas no aparecen. También puedes preparar una página en borrador.
        </div>
    </div>

    <div class="col-md-8">
        <label for="label" class="form-label">Etiqueta del menú</label>
        <input
            id="label"
            type="text"
            name="label"
            class="form-control @error('label') is-invalid @enderror"
            value="{{ old('label', $item->label) }}"
            maxlength="80"
            required
        >
        @error('label')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Puedes usar el título de la página como sugerencia; no se sincronizará automáticamente.</div>
    </div>

    <div class="col-md-4">
        <label for="sort_order" class="form-label">Orden entre páginas CMS</label>
        <input
            id="sort_order"
            type="number"
            name="sort_order"
            class="form-control @error('sort_order') is-invalid @enderror"
            value="{{ old('sort_order', $item->sort_order ?? 0) }}"
            min="0"
            required
        >
        @error('sort_order')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <div class="form-check">
            <input
                id="is_active"
                type="checkbox"
                name="is_active"
                value="1"
                class="form-check-input @error('is_active') is-invalid @enderror"
                {{ old('is_active', $item->is_active) ? 'checked' : '' }}
            >
            <label for="is_active" class="form-check-label">Activo en el menú público</label>
            @error('is_active')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-text">
            Activarlo no publica la página. Sólo aparecerá cuando la página CMS esté efectivamente publicada.
        </div>
    </div>

    <div class="col-12 d-flex gap-2 pt-2">
        <button type="submit" class="btn btn-primary">Guardar elemento</button>
        <a href="{{ route('admin.cms-navigation.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</div>
