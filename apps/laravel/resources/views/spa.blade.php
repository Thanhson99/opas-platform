<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-name" content="{{ config('opas.brand.name', 'OPAS') }}">
    <link rel="icon" type="image/png" href="{{ asset('storage/images/brand/opas-tab-icon.png') }}">
    <link rel="shortcut icon" href="{{ asset('storage/images/brand/opas-tab-icon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('storage/images/brand/opas-tab-icon.png') }}">
    <meta property="og:image" content="{{ asset('storage/images/brand/opas-banner.png') }}">
    <meta name="twitter:image" content="{{ asset('storage/images/brand/opas-banner.png') }}">
    <title>{{ config('opas.brand.name', 'OPAS') }}</title>
    @viteReactRefresh
    @vite(['resources/js/app.jsx', 'resources/scss/app.scss'])
</head>
<body class="opas-body">
    <div id="root"></div>
</body>
</html>
