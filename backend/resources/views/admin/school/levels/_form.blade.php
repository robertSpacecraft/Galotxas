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
        <label for="school_program_id" class="form-label">Programa</label>
        <select
            id="school_program_id"
            name="school_program_id"
            required
            class="form-select @error('school_program_id') is-invalid @enderror"
            aria-describedby="school_program_help"
        >
            <option value="">Selecciona un programa</option>
            @foreach ($programs as $program)
                <option
                    value="{{ $program->id }}"
                    @selected((string) old('school_program_id', $level->school_program_id) === (string) $program->id)
                >
                    {{ $program->name }} ({{ $program->is_public ? 'público' : 'privado' }})
                </option>
            @endforeach
        </select>
        @error('school_program_id')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div id="school_program_help" class="form-text">
            Un nivel sólo puede ser público cuando su programa también lo es.
        </div>
    </div>

    <div class="col-md-6">
        <label for="name" class="form-label">Nombre</label>
        <input
            id="name"
            type="text"
            name="name"
            value="{{ old('name', $level->name) }}"
            maxlength="255"
            required
            class="form-control @error('name') is-invalid @enderror"
        >
        @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="minimum_age" class="form-label">Edad mínima orientativa</label>
        <input
            id="minimum_age"
            type="number"
            name="minimum_age"
            value="{{ old('minimum_age', $level->minimum_age) }}"
            min="0"
            max="255"
            class="form-control @error('minimum_age') is-invalid @enderror"
        >
        @error('minimum_age')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="maximum_age" class="form-label">Edad máxima orientativa</label>
        <input
            id="maximum_age"
            type="number"
            name="maximum_age"
            value="{{ old('maximum_age', $level->maximum_age) }}"
            min="0"
            max="255"
            class="form-control @error('maximum_age') is-invalid @enderror"
        >
        @error('maximum_age')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">
            Ambas edades son opcionales; si existen, la mínima no puede superar la máxima.
        </div>
    </div>

    <div class="col-md-4">
        <label for="sort_order" class="form-label">Orden</label>
        <input
            id="sort_order"
            type="number"
            name="sort_order"
            value="{{ old('sort_order', $level->sort_order ?? 0) }}"
            min="0"
            max="65535"
            required
            class="form-control @error('sort_order') is-invalid @enderror"
        >
        @error('sort_order')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <input type="hidden" name="is_active" value="0">
        <div class="form-check">
            <input
                id="is_active"
                type="checkbox"
                name="is_active"
                value="1"
                class="form-check-input @error('is_active') is-invalid @enderror"
                @checked((bool) old('is_active', $level->is_active ?? false))
            >
            <label for="is_active" class="form-check-label">Nivel activo</label>
            @error('is_active')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
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
                @checked((bool) old('is_public', $level->is_public ?? false))
            >
            <label for="is_public" class="form-check-label">Nivel público</label>
            @error('is_public')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-text">
            La visibilidad efectiva exige nivel activo y programa público.
        </div>
    </div>

    <div class="col-12 d-flex gap-2 pt-2">
        <button type="submit" class="btn btn-primary">Guardar</button>
        <a href="{{ route('admin.school.levels.index') }}" class="btn btn-outline-secondary">
            Cancelar
        </a>
    </div>
</div>
