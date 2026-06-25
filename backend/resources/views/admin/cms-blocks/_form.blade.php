@csrf

@php
    $data = $block->data ?? [];
    $currentType = old('type', $block->type?->value ?? 'text');
    $itemsText = old('items_text', implode("\n", $data['items'] ?? []));
    $galleryUrlsText = old('gallery_urls_text', implode("\n", $data['urls'] ?? []));
@endphp

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
        <label for="type" class="form-label">Tipo</label>
        <select
            id="type"
            name="type"
            class="form-select @error('type') is-invalid @enderror"
            required
        >
            @foreach ($typeOptions as $value => $label)
                <option value="{{ $value }}" {{ $currentType === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('type')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="sort_order" class="form-label">Orden</label>
        <input
            id="sort_order"
            type="number"
            name="sort_order"
            class="form-control @error('sort_order') is-invalid @enderror"
            value="{{ old('sort_order', $block->sort_order ?? 10) }}"
            min="0"
            max="65535"
            required
        >
        @error('sort_order')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-8" data-block-field data-types="heading text">
        <label for="text" class="form-label">Texto</label>
        <textarea
            id="text"
            name="text"
            class="form-control @error('text') is-invalid @enderror"
            rows="4"
        >{{ old('text', $data['text'] ?? '') }}</textarea>
        @error('text')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4" data-block-field data-types="heading">
        <label for="level" class="form-label">Nivel de encabezado</label>
        <select id="level" name="level" class="form-select @error('level') is-invalid @enderror">
            @for ($level = 1; $level <= 6; $level++)
                <option value="{{ $level }}" {{ (int) old('level', $data['level'] ?? 2) === $level ? 'selected' : '' }}>
                    H{{ $level }}
                </option>
            @endfor
        </select>
        @error('level')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12" data-block-field data-types="list">
        <label for="items_text" class="form-label">Elementos de lista</label>
        <textarea
            id="items_text"
            name="items_text"
            class="form-control @error('items_text') is-invalid @enderror"
            rows="5"
            placeholder="Un elemento por línea"
        >{{ $itemsText }}</textarea>
        @error('items_text')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-8" data-block-field data-types="image button document_link">
        <label for="url" class="form-label">URL</label>
        <input
            id="url"
            type="text"
            name="url"
            class="form-control @error('url') is-invalid @enderror"
            value="{{ old('url', $data['url'] ?? '') }}"
            placeholder="/ruta-interna o https://example.com"
        >
        @error('url')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4" data-block-field data-types="image">
        <label for="alt" class="form-label">Texto alternativo</label>
        <input
            id="alt"
            type="text"
            name="alt"
            class="form-control @error('alt') is-invalid @enderror"
            value="{{ old('alt', $data['alt'] ?? '') }}"
        >
        @error('alt')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12" data-block-field data-types="gallery">
        <label for="gallery_urls_text" class="form-label">URLs de galería</label>
        <textarea
            id="gallery_urls_text"
            name="gallery_urls_text"
            class="form-control @error('gallery_urls_text') is-invalid @enderror"
            rows="5"
            placeholder="Una URL por línea"
        >{{ $galleryUrlsText }}</textarea>
        @error('gallery_urls_text')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4" data-block-field data-types="button document_link">
        <label for="label" class="form-label">Etiqueta</label>
        <input
            id="label"
            type="text"
            name="label"
            class="form-control @error('label') is-invalid @enderror"
            value="{{ old('label', $data['label'] ?? '') }}"
        >
        @error('label')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 d-flex gap-2 pt-2">
        <button type="submit" class="btn btn-primary">Guardar bloque</button>
        <a href="{{ route('admin.cms-pages.show', $page) }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const typeInput = document.getElementById('type');
            const fieldGroups = document.querySelectorAll('[data-block-field]');

            function updateVisibleFields() {
                const selectedType = typeInput.value;

                fieldGroups.forEach(function (group) {
                    const visibleTypes = group.dataset.types.split(' ');
                    group.classList.toggle('d-none', !visibleTypes.includes(selectedType));
                });
            }

            if (typeInput) {
                typeInput.addEventListener('change', updateVisibleFields);
                updateVisibleFields();
            }
        });
    </script>
@endpush
