@extends('admin.layout')

@section('title', 'Detalle de usuario')

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-0">Detalle de usuario</h1>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
                Volver
            </a>

            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary">
                Editar
            </a>
        </div>
    </div>

    <div class="card page-card">
        <div class="card-body">
            <div class="row g-3">

                <div class="col-md-3">
                    <strong>ID</strong>
                    <div>{{ $user->id }}</div>
                </div>

                <div class="col-md-3">
                    <strong>Nombre</strong>
                    <div>{{ $user->name }}</div>
                </div>

                <div class="col-md-3">
                    <strong>Apellidos</strong>
                    <div>{{ $user->lastname ?: '—' }}</div>
                </div>

                <div class="col-md-3">
                    <strong>Email</strong>
                    <div>{{ $user->email }}</div>
                </div>

                <div class="col-md-3">
                    <strong>Rol</strong>
                    <div>
                        <span class="badge text-bg-secondary">
                            {{ $user->role }}
                        </span>
                    </div>
                </div>

                <div class="col-md-3">
                    <strong>Activo</strong>
                    <div>
                        @if($user->active)
                            <span class="badge text-bg-success">Sí</span>
                        @else
                            <span class="badge text-bg-danger">No</span>
                        @endif
                    </div>
                </div>

                <div class="col-md-3">
                    <strong>Jugador</strong>
                    <div>
                        @if($user->player)
                            <span class="badge text-bg-primary">Sí</span>
                        @else
                            <span class="badge text-bg-light">No</span>
                        @endif
                    </div>
                </div>

                @if($user->player)
                    <div class="col-12 mt-3">
                        <div class="alert alert-info d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Perfil deportivo asociado</strong>
                            </div>

                            <a href="{{ route('admin.players.show', $user->player) }}"
                               class="btn btn-sm btn-outline-primary">
                                Ver jugador
                            </a>
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>
@endsection
