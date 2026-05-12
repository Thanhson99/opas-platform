<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', '404 - Not Found')</title>

    <!-- Global CSS -->
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

    <!-- Main App CSS -->
    @vite(['resources/scss/app.scss'])
</head>
<body id="@yield('body_id')">

    <div class="error-container">
        @yield('content')
    </div>

    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

    <!-- Main App JS -->
    @vite(['resources/js/app.js'])
</body>
</html>
