<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased bg-neutral-950 text-neutral-100 font-sans min-h-screen flex items-center justify-center">
    <main class="max-w-xl px-6 text-center">
        <h1 class="text-3xl font-semibold mb-2">{{ config('app.name') }}</h1>
        <p class="text-neutral-400">
            Laravel {{ app()->version() }} &middot; PHP {{ PHP_VERSION }} &middot; running on Octane/FrankenPHP.
        </p>
    </main>
</body>
</html>
