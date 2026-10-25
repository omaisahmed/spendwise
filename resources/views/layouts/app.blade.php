@php
    $appSettings = \App\Support\AppSettings::get();
    $themeSetting = $appSettings->theme ?: 'system';
    $initialTheme = $themeSetting === 'dark' ? 'dark' : 'light';
@endphp
<!DOCTYPE html>
<html lang="en" data-theme="{{ $initialTheme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#635BFF">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>@yield('title', 'SpendWise') · SpendWise</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='18' fill='%23635BFF'/%3E%3Cpath d='M19 25h26M19 33h18M19 41h11' stroke='white' stroke-width='5' stroke-linecap='round'/%3E%3C/svg%3E">
    <script>
        (() => {
            const setting = @json($themeSetting);
            const dark = setting === 'dark' || (setting === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.dataset.theme = dark ? 'dark' : 'light';
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="{{ $appSettings->privacy_mode ? 'hide-amounts' : '' }}">
    <div class="app-frame">
        @include('partials.topbar')

        <main class="page">
            @yield('content')
        </main>
    </div>

    @include('partials.bottom-nav')
    @include('partials.flash')
</body>
</html>
