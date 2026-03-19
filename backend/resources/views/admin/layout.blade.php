<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel Admin - Galotxas</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >

    <style>
        body {
            background-color: #f4f6f9;
        }

        .admin-navbar {
            background: linear-gradient(90deg, #1f2937 0%, #111827 100%);
        }

        .admin-navbar .navbar-brand,
        .admin-navbar .nav-link,
        .admin-navbar .navbar-text {
            color: #f9fafb !important;
        }

        .page-card {
            border: 0;
            box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.08);
        }

        .section-title {
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .table thead th {
            vertical-align: middle;
            white-space: nowrap;
        }

        .match-form-row input,
        .match-form-row select {
            min-width: 110px;
        }
    </style>

    @stack('styles')
</head>
<body>

<nav class="navbar navbar-expand-lg admin-navbar mb-4">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="/admin">Galotxas Admin</a>

        <button class="navbar-toggler bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="/admin">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/admin/seasons">Temporadas</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('admin.players.index') }}">Jugadores</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('admin.users.index') }}">Usuarios</a>
                </li>
            </ul>

            <form method="POST" action="{{ route('admin.logout') }}" class="d-flex">
                @csrf
                <button type="submit" class="btn btn-outline-light btn-sm">Salir</button>
            </form>
        </div>
    </div>
</nav>

<main class="container-fluid pb-4">
    @yield('content')
</main>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"
></script>

@stack('scripts')
</body>
</html>
