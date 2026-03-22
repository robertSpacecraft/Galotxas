@extends('admin.layout')

@section('title', 'Crear usuario')

@section('content')
    <div class="mb-4">
        <h1 class="h3 mb-0">Crear usuario</h1>
    </div>

    <div class="card page-card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.users.store') }}" novalidate>
                @include('admin.users._form')
            </form>
        </div>
    </div>
@endsection
