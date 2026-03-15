<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Admin - Galotxas</title>
</head>
<body>

<header>
    <h2>Panel Administración Galotxas</h2>

    <nav>
        <a href="/admin">Dashboard</a>
        <a href="/admin/seasons">Temporadas</a>
        <a href="{{ route('admin.players.index') }}">Jugadores</a>
        <a href="{{ route('admin.users.index') }}">Usuarios</a>

        <form method="POST" action="{{ route('admin.logout') }}" style="display:inline">
            @csrf
            <button type="submit">Salir</button>
        </form>
    </nav>

</header>

<hr>

<main>
    @yield('content')
</main>

</body>
</html>
