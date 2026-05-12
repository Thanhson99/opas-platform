@extends('layouts.app')

@section('title', 'Videos Trending')
@section('page_id', 'videos-trending')

@section('content_header')
    <h1>Videos Trending (Source: {{ ucfirst('Douyin') }})</h1>
@endsection

@section('content')
<h2>Douyin Trending ({{ strtoupper($period) }})</h2>

@foreach($videos as $keyword => $links)
    <h3>{{ $keyword }}</h3>
    <div style="display:flex; flex-wrap:wrap; gap:15px;">
        @foreach($links as $link)
            <video width="300" height="170" controls>
                <source src="{{ $link }}" type="video/mp4">
            </video>
        @endforeach
    </div>
@endforeach
@endsection
