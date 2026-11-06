<!doctype html>

<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class=" layout-wide  customizer-hide" dir="ltr"
    data-skin="default" data-bs-theme="light" data-assets-path="{{ asset('assets/') }}/"
    data-template="vertical-menu-template">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ isset($title) ? $title.' | '.config('app.name') : config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/img/favicon/medcred-favicon.png') }}" />
    <link rel="apple-touch-icon" href="{{ asset('assets/img/favicon/medcred-apple-touch.png') }}" />
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
