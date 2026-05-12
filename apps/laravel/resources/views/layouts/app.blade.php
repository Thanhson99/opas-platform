<!-- layouts/app.blade.php -->
@extends('adminlte::page')

<!-- Inject LayoutHelper for AdminLTE body customization -->
@inject('layoutHelper', 'JeroenNoten\LaravelAdminLte\Helpers\LayoutHelper')

@section('css')
    <!-- Global CSS -->
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

    <!-- Page-specific CSS -->
    @stack('page_css')
    <!-- Additional meta -->
    @stack('meta')

    <!-- Main App CSS -->
    @vite(['resources/scss/app.scss'])
@endsection


@php
    /**
     * Build the custom body id and data attribute for layout
     *
     * @var string $rawPageId  Page identifier from child view
     * @var string $pageId     Normalized page ID
     * @var string $bodyData   Final body attributes
     */
    $rawPageId = trim($__env->yieldContent('page_id'));

    // Normalize page ID: lowercase, replace spaces & non-alphanum with dash
    $pageId = strtolower(preg_replace('/[^a-z0-9]+/', '-', $rawPageId));

    $bodyData = trim($layoutHelper->makeBodyData() . ' id="' . $pageId . '" data-page="' . $pageId . '"');
@endphp

<!-- Output body attributes without escaping quotes -->
@section('body_data')
    {!! $bodyData !!}
@endsection

@section('js')
    <!-- Global JS dependencies -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

    <!-- Handle Laravel flash messages -->
    <script>
        @if(session('success'))
            toastr.success(@json(session('success')), 'Success');
        @endif

        @if(session('error'))
            toastr.error(@json(session('error')), 'Error');
        @endif

        @if(session('info'))
            toastr.info(@json(session('info')), 'Info');
        @endif

        @if(session('warning'))
            toastr.warning(@json(session('warning')), 'Warning');
        @endif
    </script>
    
    <!-- Page-specific JS -->
    @stack('page_js')
    @stack('scripts')

    <!-- Main App JS -->
    @vite(['resources/js/app.js'])
@endsection
