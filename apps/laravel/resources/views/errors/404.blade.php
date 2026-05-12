@extends('layouts.error-page')

@section('title', '404 - Not Found')
@section('body_id', 'error-404')

@section('content')
<div class="error-404">
    <h1 aria-hidden="true">404</h1>

    <div class="cloak__wrapper" aria-hidden="true">
        <div class="cloak__container">
            <div class="cloak"></div>
        </div>
    </div>

    <div class="info" role="note">
        <h2>We can't find that page</h2>
        <p>
            We're fairly sure that page used to be here, but seems to have gone missing.
            We do apologise on its behalf.
        </p>
        <a href="{{ url('/') }}" rel="noreferrer noopener" class="home-btn">Home</a>
    </div>
</div>
@endsection
