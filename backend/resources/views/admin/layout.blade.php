<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel Admin - Galotxas</title>

    <link href="{{ asset('css/admin.css') }}" rel="stylesheet">

    @stack('styles')
</head>
<body>

@php
    $cmsPagesActive = request()->routeIs('admin.cms-pages.*');
    $cmsNavigationActive = request()->routeIs('admin.cms-navigation.*');
    $cmsActive = $cmsPagesActive || $cmsNavigationActive;
@endphp

<nav class="navbar navbar-expand-lg admin-navbar mb-4">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="/admin">Galotxas Admin</a>

        <button
            class="navbar-toggler bg-light"
            type="button"
            data-admin-menu-toggle="#adminNavbar"
            aria-controls="adminNavbar"
            aria-expanded="false"
            aria-label="Abrir menú de administración"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="/admin">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('admin.registration-requests.index') }}">Solicitudes</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/admin/seasons">Temporadas</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('admin.venues.index') }}">Pistas</a>
                </li>
                <li class="nav-item dropdown">
                    <button
                        class="nav-link dropdown-toggle"
                        type="button"
                        id="schoolAdminDropdown"
                        data-admin-dropdown
                        aria-expanded="false"
                    >
                        Escuela de Galotxas
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="schoolAdminDropdown">
                        <li>
                            <a class="dropdown-item" href="{{ route('admin.school.enrollments.index') }}">
                                Inscripciones
                            </a>
                        </li>
                        <li>
                            <a
                                class="dropdown-item"
                                href="{{ route('admin.public-identity-authorizations.index') }}"
                            >
                                Identidad pública de menores
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('admin.school.programs.index') }}">
                                Programa
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('admin.school.levels.index') }}">
                                Niveles
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('admin.school.locations.index') }}">
                                Ubicaciones
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('admin.school.schedules.index') }}">
                                Horarios
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a
                                class="dropdown-item"
                                href="{{ route('admin.school.educational-centers.index') }}"
                            >
                                Centros educativos
                            </a>
                        </li>
                        <li>
                            <a
                                class="dropdown-item"
                                href="{{ route('admin.school.educational-activities.index') }}"
                            >
                                Actividades con centros
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('admin.match-conflicts.index') }}">Conflictos</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('admin.players.index') }}">Jugadores</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('admin.users.index') }}">Usuarios</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('admin.rankings.history') }}">Ranking histórico</a>
                </li>
                <li class="nav-item dropdown">
                    <button
                        class="nav-link dropdown-toggle{{ $cmsActive ? ' active' : '' }}"
                        type="button"
                        id="cmsAdminDropdown"
                        data-admin-dropdown
                        aria-controls="cmsAdminMenu"
                        aria-expanded="{{ $cmsActive ? 'true' : 'false' }}"
                    >
                        CMS
                    </button>
                    <ul
                        class="dropdown-menu{{ $cmsActive ? ' show' : '' }}"
                        id="cmsAdminMenu"
                        aria-labelledby="cmsAdminDropdown"
                    >
                        <li>
                            <a
                                class="dropdown-item{{ $cmsPagesActive ? ' active' : '' }}"
                                href="{{ route('admin.cms-pages.index') }}"
                                @if ($cmsPagesActive) aria-current="page" @endif
                            >
                                Páginas
                            </a>
                        </li>
                        <li>
                            <a
                                class="dropdown-item{{ $cmsNavigationActive ? ' active' : '' }}"
                                href="{{ route('admin.cms-navigation.index') }}"
                                @if ($cmsNavigationActive) aria-current="page" @endif
                            >
                                Navegación
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('admin.news-articles.index') }}">Noticias</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('admin.sponsors.index') }}">Colaboradores</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('admin.contact-requests.index') }}">Contacto</a>
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

<script src="{{ asset('js/admin.js') }}" defer></script>

@stack('scripts')
</body>
</html>
