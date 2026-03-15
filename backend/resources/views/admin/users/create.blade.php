@extends('admin.layout')

@section('content')

    <h1>Crear usuario</h1>

    <form method="POST" action="{{ route('admin.users.store') }}">

        @include('admin.users._form')

    </form>

@endsection
