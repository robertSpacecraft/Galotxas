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
        <label for="title" class="form-label">Título</label>
        <input
            id="title"
            type="text"
            name="title"
            class="form-control @error('title') is-invalid @enderror"
            value="{{ old('title', $page->title) }}"
            required
        >
        @error('title')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="slug" class="form-label">Slug</label>
        <input
            id="slug"
            type="text"
            name="slug"
            class="form-control @error('slug') is-invalid @enderror"
            value="{{ old('slug', $page->slug) }}"
            required
            placeholder="federarse"
        >
        @error('slug')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Usar minúsculas, números y guiones.</div>
    </div>

    <div class="col-md-6">
        <label for="status" class="form-label">Estado</label>
        <select
            id="status"
            name="status"
            class="form-select @error('status') is-invalid @enderror"
            required
        >
            @foreach ($statusOptions as $value => $label)
                <option value="{{ $value }}" {{ old('status', $page->status?->value ?? 'draft') === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('status')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="published_at" class="form-label">Fecha de publicación</label>
        <input
            id="published_at"
            type="datetime-local"
            name="published_at"
            class="form-control @error('published_at') is-invalid @enderror"
            value="{{ old('published_at', $page->published_at?->format('Y-m-d\TH:i')) }}"
        >
        @error('published_at')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Si se publica sin fecha, se completará con la fecha actual.</div>
    </div>

    <div class="col-md-6">
        <label for="seo_title" class="form-label">Título SEO</label>
        <input
            id="seo_title"
            type="text"
            name="seo_title"
            class="form-control @error('seo_title') is-invalid @enderror"
            value="{{ old('seo_title', $page->seo_title) }}"
        >
        @error('seo_title')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <label for="seo_description" class="form-label">Descripción SEO</label>
        <textarea
            id="seo_description"
            name="seo_description"
            class="form-control @error('seo_description') is-invalid @enderror"
            rows="3"
        >{{ old('seo_description', $page->seo_description) }}</textarea>
        @error('seo_description')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 d-flex gap-2 pt-2">
        <button type="submit" class="btn btn-primary">Guardar</button>
        <a href="{{ route('admin.cms-pages.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</div>
