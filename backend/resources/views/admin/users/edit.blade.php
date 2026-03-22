@extends('admin.layout')

@section('title', 'Editar usuario')

@section('content')
    <div class="mb-4">
        <h1 class="h3 mb-0">Editar usuario</h1>
    </div>

    <div class="card page-card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.users.update', $user) }}" novalidate>
                @method('PUT')

                @include('admin.users._form')
            </form>
        </div>
    </div>
@endsection
