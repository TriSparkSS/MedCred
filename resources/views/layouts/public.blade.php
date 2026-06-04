<!doctype html>

<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="layout-wide" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? config('app.name') }}</title>
    @include('layouts.partials.style')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
    <div class="layout-wrapper">
        <!-- Navbar -->
        <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached bg-navbar-light"
            data-bs-theme="light">
            <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0">
                <a class="nav-item nav-link px-0 me-xl-4" href="/">
                    <span>{{ config('app.name') }}</span>
                </a>
            </div>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#layout-navbar-collapse">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="layout-navbar-collapse">
                <div class="navbar-nav ms-auto">
                    <a class="nav-item nav-link" href="{{ route('privacy-policy') }}">Privacy Policy</a>
                    <a class="nav-item nav-link" href="{{ route('terms-conditions') }}">Terms & Conditions</a>
                </div>
            </div>
        </nav>

        <!-- Content -->
        <div class="layout-page">
            <div class="container-xxl flex-grow-1 container-p-y">
                {{ $slot }}
            </div>

            <!-- Footer -->
            <footer class="content-footer footer bg-footer-theme">
                <div class="container-xxl d-flex flex-wrap justify-content-between py-2 flex-md-row flex-column">
                    <div class="mb-2 mb-md-0">
                        <span class="text-muted">© 2024 {{ config('app.name') }}. All rights reserved.</span>
                    </div>
                </div>
            </footer>

            <div class="content-backdrop fade"></div>
        </div>
    </div>

    @include('layouts.partials.scripts')
</body>

</html>
