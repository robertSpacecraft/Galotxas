@extends('admin.layout')

@section('content')
    <div class="container-fluid px-0">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-2">Editar noticia</h1>
                <p class="text-secondary mb-0">{{ $article->title }}</p>
            </div>
            <a href="{{ route('admin.news-articles.index') }}" class="btn btn-outline-secondary">Volver</a>
        </div>

        @if (session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif

        <div class="card shadow-sm">
            <div class="card-body">
                <form
                    method="POST"
                    action="{{ route('admin.news-articles.update', $article) }}"
                    enctype="multipart/form-data"
                >
                    @method('PUT')
                    @include('admin.news-articles._form')
                </form>
            </div>
        </div>
    </div>
@endsection
