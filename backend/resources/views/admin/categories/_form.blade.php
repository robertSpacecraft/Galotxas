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
    <div class="col-12">
        <label for="championship_name" class="form-label">Campeonato</label>
        <input
            id="championship_name"
            type="text"
            class="form-control"
            value="{{ $championship->name }}"
            disabled
        >
        <div class="form-text">
            La categoría permanece asociada a este campeonato.
            Campeonato: {{ $championship->is_public ? 'Público' : 'Privado' }}.
            Temporada: {{ $championship->season?->is_public ? 'Pública' : 'Privada' }}.
        </div>
    </div>

    <div class="col-md-6">
        <label for="name" class="form-label">Nombre</label>
        <input
            id="name"
            type="text"
            name="name"
            class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $category->name) }}"
            maxlength="255"
            required
        >
        @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-2">
        <label for="level" class="form-label">Nivel</label>
        <select
            id="level"
            name="level"
            class="form-select @error('level') is-invalid @enderror"
        >
            <option value="">Sin nivel</option>
            @foreach ($levelOptions as $level)
                <option
                    value="{{ $level }}"
                    @selected((string) old('level', $category->level) === (string) $level)
                >{{ $level }}</option>
            @endforeach
        </select>
        @error('level')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-2">
        <label for="gender" class="form-label">Género</label>
        <select
            id="gender"
            name="gender"
            class="form-select @error('gender') is-invalid @enderror"
            required
        >
            <option value="">Selecciona género</option>
            @foreach ($genderOptions as $gender)
                <option
                    value="{{ $gender->value }}"
                    @selected(old('gender', $category->gender?->value) === $gender->value)
                >{{ $gender->label() }}</option>
            @endforeach
        </select>
        @error('gender')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-2">
        <label for="status" class="form-label">Estado</label>
        <select
            id="status"
            name="status"
            class="form-select @error('status') is-invalid @enderror"
            required
        >
            @foreach ($statusOptions as $status)
                <option
                    value="{{ $status->value }}"
                    @selected(old('status', $category->status ?? $defaultStatus) === $status->value)
                >{{ $status->label() }}</option>
            @endforeach
        </select>
        @error('status')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <label for="description" class="form-label">Descripción</label>
        <textarea
            id="description"
            name="description"
            class="form-control @error('description') is-invalid @enderror"
            rows="4"
            maxlength="5000"
        >{{ old('description', $category->description) }}</textarea>
        @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <input type="hidden" name="is_public" value="0">
        <div class="form-check">
            <input
                id="is_public"
                type="checkbox"
                name="is_public"
                value="1"
                class="form-check-input @error('is_public') is-invalid @enderror"
                aria-describedby="is_public_help"
                @checked((bool) old('is_public', $category->is_public ?? false))
            >
            <label for="is_public" class="form-check-label">Visible públicamente</label>
            @error('is_public')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div id="is_public_help" class="form-text">
            Requiere que tanto el campeonato como su temporada sean públicos. El estado operativo es independiente.
        </div>
    </div>

    <div class="col-12 d-flex gap-2 pt-2">
        <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
        <a href="{{ route('admin.championships.categories', $championship) }}"
           class="btn btn-outline-secondary">
            Cancelar
        </a>
    </div>
</div>
