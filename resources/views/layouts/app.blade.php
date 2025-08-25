<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Lighthouse') }}</title>
    <link rel="stylesheet" href="/css/app.css">
    @livewireStyles
</head>
<body>
    <div class="container mx-auto p-4">
        {{ $slot }}
    </div>
    @livewireScripts
</body>
</html>
