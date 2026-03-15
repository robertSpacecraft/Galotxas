@csrf

<div>

    <label>Nombre</label>

    <input type="text"
           name="name"
           value="{{ old('name', $user->name ?? '') }}">

</div>

<div>

    <label>Email</label>

    <input type="email"
           name="email"
           value="{{ old('email', $user->email ?? '') }}">

</div>

<div>

    <label>Contraseña</label>

    <input type="password" name="password">

</div>

<div>

    <label>Confirmar contraseña</label>

    <input type="password" name="password_confirmation">

</div>

<div>

    <label>Rol</label>

    <select name="role">

        @foreach($roleOptions as $value => $label)

            <option value="{{ $value }}"
                {{ old('role', $user->role ?? '') == $value ? 'selected' : '' }}>
                {{ $label }}
            </option>

        @endforeach

    </select>

</div>

<div>

    <label>Activo</label>

    <input type="checkbox"
           name="active"
           value="1"
        {{ old('active', $user->active ?? true) ? 'checked' : '' }}>

</div>

<br>

<button type="submit">
    Guardar
</button>
