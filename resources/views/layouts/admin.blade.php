<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    class=" layout-navbar-fixed layout-menu-fixed layout-compact " dir="ltr" data-skin="default" data-bs-theme="light"
    data-assets-path="{{ asset('assets/') }}/" data-template="vertical-menu-template-starter">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {{-- <style>
        :root {
            --bs-primary: '#0F2061';

            --bs-primary-rgb: rgb('#0F2061');
        }
    </style> --}}
    @stack('styles')
    <style>
        :root {

            --bs-primary: #091572;

            --bs-secondary: #808390;

            --bs-success: #28c76f;

            --bs-danger: #ff4c51;


            --bs-primary-rgb: rgb(#091572);

            --bs-link-color: #091572;

            --bs-link-hover-color: #685dd8;

        }
    </style>

    <title>{{ $title ?? __('admin.dashboard') }}</title>
    @include('layouts.partials.admin-styles')

</head>

<body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar ">
        <div class="layout-container">
            {{-- Sidebar --}}
            {{-- @livewire('components.admin.partials.sidebar') --}}
            <livewire:admin.partials.sidebar :key="'sidebar-' . app()->getLocale()" />
            <!-- Layout container -->
            <div class="layout-page">

                {{-- @livewire('components.admin.partials.header') --}}
                <livewire:admin.partials.header :key="'header-' . app()->getLocale()" />

                <!-- Content wrapper -->
                <div class="content-wrapper">
                    {{ $slot }}
                    <!-- Footer -->
                </div>
                {{-- @livewire('components.admin.partials.footer') --}}
            </div>
        </div>
    </div>
    <div class="layout-overlay"></div>
    <div class="drag-target"></div>
    @include('layouts.partials.admin-scripts')
    @stack('scripts')
</body>

</html>
