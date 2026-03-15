@extends('admin.layout')

@section('content')

    <h1>Dashboard</h1>

    <p>Bienvenido {{ auth()->user()->name }}</p>

    <p>Desde aquí podrás gestionar:</p>

    <ul>
        <li>Temporadas</li>
        <li>Campeonatos</li>
        <li>Categorías</li>
        <li>Partidos</li>
    </ul>

@endsection
