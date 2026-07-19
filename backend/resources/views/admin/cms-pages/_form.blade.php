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
    @if (!$page->exists)
        <div class="col-12">
            <div class="alert alert-info mb-0" role="status">
                <strong>La página se creará como borrador.</strong>
                Después podrás añadir uno o más bloques y publicarla desde la edición.
            </div>
            <input type="hidden" name="status" value="draft">
        </div>
    @endif

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

    @if ($page->exists)
        <div class="col-md-6">
            <label for="status" class="form-label">Estado</label>
            <select
                id="status"
                name="status"
                class="form-select @error('status') is-invalid @enderror"
                required
                aria-describedby="status-help"
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
            <div id="status-help" class="form-text">
                @if (!$hasPublishableContent)
                    Esta página no tiene bloques y no podrá publicarse hasta que añadas al menos uno válido.
                @else
                    La página tiene contenido y puede guardarse como publicada.
                @endif
            </div>
        </div>

        <div class="col-md-6">
            <label for="published_at" class="form-label">Fecha de publicación</label>
            <input
                id="published_at"
                type="datetime-local"
                name="published_at"
                class="form-control @error('published_at') is-invalid @enderror"
                value="{{ old('published_at', $page->published_at?->format('Y-m-d\TH:i')) }}"
                aria-describedby="published-at-help"
            >
            @error('published_at')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div id="published-at-help" class="form-text">
                Déjala vacía para publicar inmediatamente. Una fecha futura programa la publicación.
                La fecha se interpreta en la zona horaria configurada por Laravel: {{ $appTimezone }}.
            </div>
        </div>
    @endif

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
