<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    class="layout-navbar-fixed layout-menu-fixed layout-compact" dir="ltr" data-skin="default" data-bs-theme="light"
    data-assets-path="{{ asset('assets/') }}/" data-template="vertical-menu-template-starter">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @stack('styles')
    <style>
        :root {
            --bs-primary: #0d6e5a;
            --bs-secondary: #808390;
            --bs-success: #28c76f;
            --bs-danger: #ff4c51;
            --bs-link-color: #0d6e5a;
            --bs-link-hover-color: #0a5546;
        }
    </style>
    <title>{{ $title ?? 'Practice Portal' }}</title>
    @include('layouts.partials.admin-styles')
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <livewire:practice.partials.sidebar :key="'practice-sidebar'" />
            <div class="layout-page">
                <livewire:practice.partials.header :key="'practice-header'" />
                <div class="content-wrapper">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
    <div class="layout-overlay"></div>
    <div class="drag-target"></div>
    @include('layouts.partials.admin-scripts')
    @stack('scripts')
</body>

</html>
