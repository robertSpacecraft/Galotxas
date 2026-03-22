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
            class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $user->name ?? '') }}"
            required
        >
        @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="lastname" class="form-label">Apellidos</label>
        <input
            id="lastname"
            type="text"
            name="lastname"
            class="form-control @error('lastname') is-invalid @enderror"
            value="{{ old('lastname', $user->lastname ?? '') }}"
            required
        >
        @error('lastname')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="email" class="form-label">Email</label>
        <input
            id="email"
            type="email"
            name="email"
            class="form-control @error('email') is-invalid @enderror"
            value="{{ old('email', $user->email ?? '') }}"
            required
        >
        @error('email')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="role" class="form-label">Rol</label>
        <select
            id="role"
            name="role"
            class="form-select @error('role') is-invalid @enderror"
            required
        >
            @foreach($roleOptions as $value => $label)
                <option value="{{ $value }}"
                    {{ old('role', $user->role ?? '') == $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('role')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="password" class="form-label">
            Contraseña
            @isset($user)
                <span class="text-muted small">(dejar en blanco para no cambiarla)</span>
            @endisset
        </label>
        <input
            id="password"
            type="password"
            name="password"
            class="form-control @error('password') is-invalid @enderror"
            autocomplete="new-password"
            @empty($user) required @endempty
        >
        @error('password')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
        <input
            id="password_confirmation"
            type="password"
            name="password_confirmation"
            class="form-control"
            autocomplete="new-password"
            @empty($user) required @endempty
        >

        <div id="password-match-feedback" class="mt-2 d-none">
            <span class="text-danger d-inline-flex align-items-center gap-2">
                <span class="fw-bold">✕</span>
                <span>Las contraseñas no coinciden.</span>
            </span>
        </div>
    </div>

    <div class="col-12">
        <div class="form-check">
            <input
                id="active"
                type="checkbox"
                name="active"
                value="1"
                class="form-check-input"
                {{ old('active', $user->active ?? true) ? 'checked' : '' }}
            >
            <label for="active" class="form-check-label">Activo</label>
        </div>
    </div>

    <div class="col-12 d-flex gap-2 pt-2">
        <button type="submit" class="btn btn-primary">Guardar</button>
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const passwordInput = document.getElementById('password');
            const confirmationInput = document.getElementById('password_confirmation');
            const feedback = document.getElementById('password-match-feedback');

            function validatePasswords() {
                const password = passwordInput.value;
                const confirmation = confirmationInput.value;

                if (!password && !confirmation) {
                    feedback.classList.add('d-none');
                    confirmationInput.classList.remove('is-invalid');
                    return;
                }

                if (confirmation && password !== confirmation) {
                    feedback.classList.remove('d-none');
                    confirmationInput.classList.add('is-invalid');
                } else {
                    feedback.classList.add('d-none');
                    confirmationInput.classList.remove('is-invalid');
                }
            }

            if (passwordInput && confirmationInput && feedback) {
                passwordInput.addEventListener('input', validatePasswords);
                confirmationInput.addEventListener('input', validatePasswords);
                validatePasswords();
            }
        });
    </script>
@endpush
