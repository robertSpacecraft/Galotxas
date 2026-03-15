@extends('admin.layout')

@section('content')

    <h1>Editar usuario</h1>

    <form method="POST" action="{{ route('admin.users.update', $user) }}">

        @method('PUT')

        @include('admin.users._form')

    </form>

@endsection
