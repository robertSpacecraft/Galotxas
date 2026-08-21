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

@php
    $isEdit = $article->exists;
    $state = $isEdit ? $article->publicationState() : null;
    $slugEditable = ! $isEdit || $article->isSlugEditable();
    $currentStatus = old('status', $article->status?->value ?? 'draft');
@endphp

@if ($isEdit)
    <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>
            Estado efectivo:
            <span class="badge {{ $state->badgeClass() }}">{{ $state->label() }}</span>
        </span>
        <span class="small">
            Zona horaria editorial: {{ $appTimezone }}.
        </span>
    </div>
@else
    <input type="hidden" name="status" value="draft">
    <input type="hidden" name="published_at" value="">
@endif

<input type="hidden" name="image_rights_confirmed" value="0">
<input type="hidden" name="remove_image" value="0">

<div class="row g-3">
    <div class="col-md-8">
        <label for="title" class="form-label">Título</label>
        <input
            id="title"
            type="text"
            name="title"
            class="form-control @error('title') is-invalid @enderror"
            value="{{ old('title', $article->title) }}"
            maxlength="255"
            required
        >
        @error('title')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="slug" class="form-label">Slug</label>
        <input
            id="slug"
            type="text"
            name="slug"
            class="form-control @error('slug') is-invalid @enderror"
            value="{{ old('slug', $article->slug) }}"
            maxlength="255"
            pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
            @readonly(! $slugEditable)
        >
        <div class="form-text">
            @if ($slugEditable)
                Opcional al crear; si se deja vacío se genera desde el título.
            @else
                Bloqueado desde la primera programación o publicación.
            @endif
        </div>
        @error('slug')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <label for="excerpt" class="form-label">Resumen</label>
        <textarea
            id="excerpt"
            name="excerpt"
            rows="3"
            class="form-control @error('excerpt') is-invalid @enderror"
            maxlength="500"
            required
        >{{ old('excerpt', $article->excerpt) }}</textarea>
        <div class="form-text">Resumen manual para la portada y como descripción SEO de respaldo.</div>
        @error('excerpt')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <label for="body" class="form-label">Contenido</label>
        <textarea
            id="body"
            name="body"
            rows="12"
            class="form-control @error('body') is-invalid @enderror"
            maxlength="20000"
            required
        >{{ old('body', $article->body) }}</textarea>
        <div class="form-text">
            Sólo texto plano. Separa párrafos con una línea en blanco; no se admite HTML ni Markdown.
        </div>
        @error('body')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12"><hr></div>

    <div class="col-md-7">
        <label for="image" class="form-label">
            Imagen principal {{ $isEdit && $article->image_key ? 'nueva (opcional)' : '(opcional en borrador)' }}
        </label>
        <input
            id="image"
            type="file"
            name="image"
            class="form-control @error('image') is-invalid @enderror"
            accept="image/jpeg,image/png,image/webp"
        >
        <div class="form-text">
            JPEG, PNG o WebP; máximo 8 MB y 6000 × 6000 px. Se normaliza sin crop hasta 1920 × 1080 px.
            Se recomienda una composición 16:9 para las tarjetas.
        </div>
        @error('image')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    @if ($isEdit && $article->image_key)
        <div class="col-md-5">
            <span class="form-label d-block">Imagen actual</span>
            <img
                src="{{ route('admin.news-articles.image', $article) }}"
                alt="{{ $article->image_alt ?: 'Vista previa administrativa' }}"
                width="{{ $article->image_width }}"
                height="{{ $article->image_height }}"
                class="img-thumbnail"
                style="max-width: 320px; max-height: 180px; object-fit: contain;"
            >
        </div>
    @endif

    <div class="col-md-6">
        <label for="image_alt" class="form-label">Texto alternativo</label>
        <input
            id="image_alt"
            type="text"
            name="image_alt"
            class="form-control @error('image_alt') is-invalid @enderror"
            value="{{ old('image_alt', $article->image_alt) }}"
            maxlength="255"
        >
        <div class="form-text">Obligatorio para publicar. Describe la información relevante de la imagen.</div>
        @error('image_alt')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="image_credit" class="form-label">Crédito público (opcional)</label>
        <input
            id="image_credit"
            type="text"
            name="image_credit"
            class="form-control @error('image_credit') is-invalid @enderror"
            value="{{ old('image_credit', $article->image_credit) }}"
            maxlength="255"
        >
        @error('image_credit')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <label for="image_source" class="form-label">Procedencia editorial privada</label>
        <input
            id="image_source"
            type="text"
            name="image_source"
            class="form-control @error('image_source') is-invalid @enderror"
            value="{{ old('image_source', $article->image_source) }}"
            maxlength="500"
        >
        <div class="form-text">Obligatoria para publicar. Nunca se expone mediante la API pública.</div>
        @error('image_source')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <div class="alert alert-warning mb-2">
            Esta confirmación no crea un consentimiento. Declara que ya has verificado que el Club dispone
            de derechos y autorizaciones suficientes. La identidad pública deportiva y el avatar privado no
            autorizan fotografías; las imágenes con personas, especialmente menores, requieren evidencia
            específica previa.
        </div>
        <div class="form-check">
            <input
                id="image_rights_confirmed"
                type="checkbox"
                name="image_rights_confirmed"
                value="1"
                class="form-check-input @error('image_rights_confirmed') is-invalid @enderror"
                @checked((bool) old('image_rights_confirmed', false))
            >
            <label for="image_rights_confirmed" class="form-check-label">
                Confirmo que ya he verificado los derechos y autorizaciones aplicables a esta imagen.
            </label>
        </div>
        @if ($isEdit && $article->image_rights_confirmed_at)
            <div class="form-text">
                Última confirmación registrada:
                {{ $article->image_rights_confirmed_at->format('d/m/Y H:i') }}
                @if ($article->rightsConfirmedBy)
                    por {{ $article->rightsConfirmedBy->name }}.
                @endif
                Debes confirmar de nuevo para guardar la noticia como publicada.
            </div>
        @endif
        @error('image_rights_confirmed')
        <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    @if ($isEdit && $article->image_key)
        <div class="col-12">
            <div class="form-check">
                <input
                    id="remove_image"
                    type="checkbox"
                    name="remove_image"
                    value="1"
                    class="form-check-input @error('remove_image') is-invalid @enderror"
                    @checked((bool) old('remove_image', false))
                >
                <label for="remove_image" class="form-check-label">Retirar la imagen actual</label>
                <div class="form-text">Sólo es posible si la noticia queda como borrador.</div>
            </div>
            @error('remove_image')
            <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    @endif

    @if ($isEdit)
        <div class="col-12"><hr></div>

        <div class="col-md-6">
            <label for="status" class="form-label">Estado editorial</label>
            <select
                id="status"
                name="status"
                class="form-select @error('status') is-invalid @enderror"
                required
            >
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}" @selected($currentStatus === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <div class="form-text">
                Publicada sin fecha futura se publica ahora; una fecha futura la deja programada.
            </div>
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
                value="{{ old('published_at', $article->published_at?->format('Y-m-d\TH:i')) }}"
                @readonly($article->hasBeenEffectivelyPublished())
            >
            <div class="form-text">
                Zona horaria: {{ $appTimezone }}. La fecha queda bloqueada tras la publicación efectiva.
            </div>
            @error('published_at')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    @endif

    <div class="col-md-6">
        <label for="seo_title" class="form-label">Título SEO (opcional)</label>
        <input
            id="seo_title"
            type="text"
            name="seo_title"
            class="form-control @error('seo_title') is-invalid @enderror"
            value="{{ old('seo_title', $article->seo_title) }}"
            maxlength="255"
        >
        @error('seo_title')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="seo_description" class="form-label">Descripción SEO (opcional)</label>
        <textarea
            id="seo_description"
            name="seo_description"
            rows="3"
            class="form-control @error('seo_description') is-invalid @enderror"
            maxlength="500"
        >{{ old('seo_description', $article->seo_description) }}</textarea>
        @error('seo_description')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 d-flex gap-2 pt-2">
        <button type="submit" class="btn btn-primary">Guardar noticia</button>
        <a href="{{ route('admin.news-articles.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</div>
