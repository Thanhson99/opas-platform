@extends('layouts.app')

@section('title', 'Create Feed Keyword')
@section('page_id', 'keywords')

@section('content_header')
    <h1>Create Feed Keyword</h1>
@endsection

@section('content')
<div class="container mt-4">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('coins.feed-keywords.store') }}" id="keywordForm">
        @csrf

        <!-- Keyword -->
        <div class="mb-3">
            <label for="keyword" class="form-label">Keyword</label>
            <input
                type="text"
                name="keyword"
                id="keyword"
                class="form-control @error('keyword') is-invalid @enderror"
                placeholder="Enter keyword (required)"
                required
            >
            @error('keyword')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <!-- Tags -->
        <div class="mb-3">
            <label class="form-label">Tags</label>
            <div class="row gx-2">
                <div class="col-md-8">
                    <input
                        type="text"
                        id="tagInput"
                        class="form-control"
                        placeholder="Optional tag (press Enter)"
                    >
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-success w-100">Create</button>
                </div>
            </div>
        </div>

        <!-- Hidden inputs will be appended here (must be inside form!) -->
        <div id="hiddenTagInputs"></div>
    </form>

    <hr>

    <!-- Tag preview -->
    <ul class="list-group mt-4">
        @forelse($keywords as $item)
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    {{ $item->keyword }}
                    @foreach ($item->tags as $tag)
                        <span class="badge bg-primary ms-1">#{{ $tag->name }}</span>
                    @endforeach
                </div>
                <form action="{{ route('coins.feed-keywords.destroy', $item->id) }}" method="POST" onsubmit="return confirm('Delete this keyword?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm">❌</button>
                </form>
            </li>
        @empty
            <li class="list-group-item">No keyword found.</li>
        @endforelse
    </ul>
</div>
@endsection
