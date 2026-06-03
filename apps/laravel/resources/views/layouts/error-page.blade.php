<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', '404 - Not Found')</title>
    @vite(['resources/scss/error-page.scss'])
</head>
<body id="@yield('body_id')">

    <div class="error-container">
        @yield('content')
    </div>
</body>
</html>
