<!doctype html>

<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class=" layout-wide  customizer-hide" dir="ltr"
    data-skin="default" data-bs-theme="light" data-assets-path="{{ asset('assets/') }}/"
    data-template="vertical-menu-template">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? config('app.name') }}</title>
    @include('layouts.partials.style')
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
</head>

<body>
    {{ $slot }}

    @livewireScripts
    @include('layouts.partials.scripts')
</body>

</html>
