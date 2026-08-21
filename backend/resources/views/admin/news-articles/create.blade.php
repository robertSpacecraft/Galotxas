@extends('admin.layout')

@section('content')
    <div class="container-fluid px-0">
        <div class="d-flex justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Crear noticia</h1>
                <p class="text-secondary mb-0">La nueva noticia se guardará siempre como borrador.</p>
            </div>
            <a href="{{ route('admin.news-articles.index') }}" class="btn btn-outline-secondary">Volver</a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <form
                    method="POST"
                    action="{{ route('admin.news-articles.store') }}"
                    enctype="multipart/form-data"
                >
                    @include('admin.news-articles._form')
                </form>
            </div>
        </div>
    </div>
@endsection
