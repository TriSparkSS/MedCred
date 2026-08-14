<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    class=" layout-navbar-fixed layout-menu-fixed layout-compact " dir="ltr" data-skin="default" data-bs-theme="light"
    data-assets-path="{{ asset('assets/') }}/" data-template="vertical-menu-template-starter">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    @stack('styles')
    <title>{{ $title ?? __('seller.seller_dashboard') }}</title>
    @include('layouts.partials.admin-styles')

</head>

<body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar ">
        <div class="layout-container">
            {{-- Sidebar --}}
            <livewire:seller.partials.sidebar :key="'sidebar-' . app()->getLocale()" />
            <!-- Layout container -->
            <div class="layout-page">

                {{-- @livewire('components.seller.partials.header') --}}
                <livewire:seller.partials.header :key="'header-' . app()->getLocale()" />

                <!-- Content wrapper -->
                <div class="content-wrapper">
                    {{ $slot }}
                    <!-- Footer -->
                </div>
                {{-- @livewire('components.seller.partials.footer') --}}
            </div>
        </div>
    </div>
    <div class="layout-overlay"></div>
    <div class="drag-target"></div>
    @include('layouts.partials.admin-scripts')
    @stack('scripts')
</body>

</html>
