@extends('admin.layout')

@section('title', 'Editar jugador')

@section('content')
    <div class="mb-4">
        <h1 class="h3 mb-0">Editar jugador</h1>
    </div>

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

    <div class="card shadow-sm">
        <div class="card-body">

            <form action="{{ route('admin.players.update', $player) }}" method="POST">

                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label">Usuario</label>

                    <select name="user_id" class="form-select" required>
                        <option value="">Seleccionar usuario</option>

                        @foreach ($users as $user)
                            <option
                                value="{{ $user->id }}"
                                {{ old('user_id', $player->user_id) == $user->id ? 'selected' : '' }}>
                                {{ $user->name }} ({{ $user->email }})
                            </option>
                        @endforeach

                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">DNI</label>

                    <input
                        type="text"
                        name="dni"
                        class="form-control"
                        value="{{ old('dni', $player->dni) }}"
                        placeholder="Opcional"
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Fecha de nacimiento</label>

                    <input
                        type="date"
                        name="birth_date"
                        class="form-control"
                        value="{{ old('birth_date', optional($player->birth_date)->format('Y-m-d')) }}"
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Género</label>

                    <select name="gender" class="form-select">

                        <option value="">Sin especificar</option>

                        @foreach ($genderOptions as $value => $label)
                            <option
                                value="{{ $value }}"
                                {{ old('gender', $player->gender?->value) == $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach

                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Nivel</label>

                    <input
                        type="number"
                        name="level"
                        class="form-control"
                        value="{{ old('level', $player->level) }}"
                        min="1"
                        max="10"
                        required
                    >
                </div>

                <div class="form-check mb-4">

                    <input
                        type="checkbox"
                        name="active"
                        value="1"
                        class="form-check-input"
                        id="active"
                        {{ old('active', $player->active) ? 'checked' : '' }}
                    >

                    <label class="form-check-label" for="active">
                        Jugador activo
                    </label>

                </div>

                <div class="d-flex justify-content-between">

                    <a href="{{ route('admin.players.index') }}" class="btn btn-secondary">
                        Cancelar
                    </a>

                    <button type="submit" class="btn btn-primary">
                        Guardar cambios
                    </button>

                </div>

            </form>

        </div>
    </div>
@endsection
