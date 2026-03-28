@extends('admin.layout')

@section('title', 'Dashboard')

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Dashboard</h1>
            <p class="text-secondary mb-0">
                Panel general de administración de Galotxas
            </p>
        </div>
    </div>

    <div class="card page-card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Bienvenido</h2>
            <p class="mb-0">
                Has iniciado sesión como <strong>{{ auth()->user()->name }}</strong>.
                Desde aquí puedes acceder a las principales áreas de gestión del sistema.
            </p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6 col-xl-3">
            <div class="card page-card h-100">
                <div class="card-body d-flex flex-column">
                    <h2 class="h5 mb-2">Temporadas</h2>
                    <p class="text-secondary mb-4">
                        Consulta y gestiona las temporadas disponibles del sistema.
                    </p>

                    <div class="mt-auto">
                        <a href="{{ route('admin.seasons.index') }}" class="btn btn-primary w-100">
                            Ir a temporadas
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card page-card h-100">
                <div class="card-body d-flex flex-column">
                    <h2 class="h5 mb-2">Campeonatos</h2>
                    <p class="text-secondary mb-4">
                        Revisa campeonatos, rankings, solicitudes de inscripción y estados.
                    </p>

                    <div class="mt-auto">
                        <a href="{{ route('admin.championships.index') }}" class="btn btn-primary w-100">
                            Ir a campeonatos
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card page-card h-100">
                <div class="card-body d-flex flex-column">
                    <h2 class="h5 mb-2">Usuarios</h2>
                    <p class="text-secondary mb-4">
                        Administra usuarios, perfiles y acceso al sistema.
                    </p>

                    <div class="mt-auto">
                        <a href="{{ route('admin.users.index') }}" class="btn btn-primary w-100">
                            Ir a usuarios
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card page-card h-100">
                <div class="card-body d-flex flex-column">
                    <h2 class="h5 mb-2">Jugadores</h2>
                    <p class="text-secondary mb-4">
                        Accede a los perfiles deportivos de los jugadores registrados.
                    </p>

                    <div class="mt-auto">
                        <a href="{{ route('admin.players.index') }}" class="btn btn-primary w-100">
                            Ir a jugadores
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-lg-8">
            <div class="card page-card h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Áreas de gestión disponibles</h2>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="border rounded p-3 bg-light h-100">
                                <h3 class="h6 mb-2">Competición</h3>
                                <ul class="mb-0 ps-3">
                                    <li>Temporadas y campeonatos</li>
                                    <li>Categorías e inscripciones</li>
                                    <li>Generación de liga y copa</li>
                                    <li>Resultados y validaciones</li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="border rounded p-3 bg-light h-100">
                                <h3 class="h6 mb-2">Personas y acceso</h3>
                                <ul class="mb-0 ps-3">
                                    <li>Usuarios del sistema</li>
                                    <li>Perfiles de jugador</li>
                                    <li>Relación usuario / player</li>
                                    <li>Estados y permisos</li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="border rounded p-3 bg-light h-100">
                                <h3 class="h6 mb-2">Operativa deportiva</h3>
                                <ul class="mb-0 ps-3">
                                    <li>Partidos, calendario y pistas</li>
                                    <li>Reprogramaciones</li>
                                    <li>Conflictos y revisiones</li>
                                    <li>Seguimiento del estado competitivo</li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="border rounded p-3 bg-light h-100">
                                <h3 class="h6 mb-2">Rankings</h3>
                                <ul class="mb-0 ps-3">
                                    <li>Ranking por categoría</li>
                                    <li>Ranking por campeonato</li>
                                    <li>Ranking por temporada</li>
                                    <li>Ranking histórico</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card page-card h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Accesos rápidos</h2>

                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.seasons.index') }}" class="btn btn-outline-secondary">
                            Ver temporadas
                        </a>

                        <a href="{{ route('admin.championships.index') }}" class="btn btn-outline-secondary">
                            Ver campeonatos
                        </a>

                        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
                            Ver usuarios
                        </a>

                        <a href="{{ route('admin.players.index') }}" class="btn btn-outline-secondary">
                            Ver jugadores
                        </a>

                        <a href="{{ route('admin.rankings.history') }}" class="btn btn-outline-secondary">
                            Ranking histórico
                        </a>
                    </div>

                    <hr>

                    <div class="small text-secondary">
                        Usa este panel como punto de entrada a la gestión general.
                        Las tareas más específicas se realizan dentro de cada campeonato y categoría.
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
