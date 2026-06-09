<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

<div>
    <!-- Menu -->
    <aside id="layout-menu" class="layout-menu menu-vertical menu">
        <div class="app-brand demo ">
            <a href="{{ route('admin.dashboard') }}" class="app-brand-link">
                {{-- <span class="app-brand-logo demo">
                    <span class="text-primary">
                        <svg width="32" height="22" viewBox="0 0 32 22" fill="none"
                            xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd"
                                d="M0.00172773 0V6.85398C0.00172773 6.85398 -0.133178 9.01207 1.98092 10.8388L13.6912 21.9964L19.7809 21.9181L18.8042 9.88248L16.4951 7.17289L9.23799 0H0.00172773Z"
                                fill="currentColor" />
                            <path opacity="0.06" fill-rule="evenodd" clip-rule="evenodd"
                                d="M7.69824 16.4364L12.5199 3.23696L16.5541 7.25596L7.69824 16.4364Z" fill="#161616" />
                            <path opacity="0.06" fill-rule="evenodd" clip-rule="evenodd"
                                d="M8.07751 15.9175L13.9419 4.63989L16.5849 7.28475L8.07751 15.9175Z" fill="#161616" />
                            <path fill-rule="evenodd" clip-rule="evenodd"
                                d="M7.77295 16.3566L23.6563 0H32V6.88383C32 6.88383 31.8262 9.17836 30.6591 10.4057L19.7824 22H13.6938L7.77295 16.3566Z"
                                fill="currentColor" />
                        </svg>
                    </span>
                </span> --}}
                <span class="app-brand-text demo menu-text fw-bold font-playfair text-uppercase">REVANTAGE</span>
            </a>

            <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
                <i class="icon-base ti menu-toggle-icon d-none d-xl-block"></i>
                <i class="icon-base ti tabler-x d-block d-xl-none"></i>
            </a>
        </div>

        <div class="menu-inner-shadow"></div>

        <ul class="menu-inner py-1">
            <!-- Page -->
            <li class="menu-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <a href="{{ route('admin.dashboard') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-smart-home"></i>
                    <div>Dashboard</div>
                </a>
            </li>

            <li class="menu-item {{ request()->routeIs('admin.providers') ? 'active' : '' }}">
                <a href="{{ route('admin.providers') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-first-aid-kit"></i>
                    <div>Providers</div>
                </a>
            </li>
            <li class="menu-item {{ request()->routeIs('admin.practices') ? 'active' : '' }}">
                <a href="{{ route('admin.practices') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-building"></i>
                    <div>Practices</div>
                </a>
            </li>

            <li class="menu-item {{ request()->routeIs('admin.credentials') ? 'active' : '' }}">
                <a href="{{ route('admin.credentials') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-clipboard-check"></i>
                    <div>Credentialing Tracker</div>
                </a>
            </li>
            <li class="menu-item {{ request()->routeIs('admin.email.dashboard') ? 'active' : '' }}">
                <a href="{{ route('admin.email.dashboard') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-mail"></i>
                    <div>Email Center</div>
                </a>
            </li>
            <li class="menu-item {{ request()->routeIs('admin.documents') ? 'active' : '' }}">
                <a href="{{ route('admin.documents') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-file-type-doc"></i>
                    <div>Documents</div>
                </a>
            </li>


            <li class="menu-item {{ request()->routeIs('admin.tasks.kanban') ? 'active' : '' }}">
                <a href="{{ route('admin.tasks.kanban') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-list-check"></i>
                    <div>Task & Follow-ups</div>
                </a>
            </li>


            <li class="menu-item {{ request()->routeIs('admin.reports') ? 'active' : '' }}">
                <a href="{{ route('admin.reports') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-chart-bar"></i>
                    <div>Reports</div>
                </a>
            </li>


            <li
                class="menu-item {{ request()->routeIs('admin.settings*') || request()->routeIs('admin.settings.specialties*') ? 'active open' : '' }}">
                <a href="javascript:void(0);" class="menu-link menu-toggle">
                    <i class="menu-icon icon-base ti tabler-settings"></i>
                    <div>Admin Settings</div>
                </a>

                <ul class="menu-sub">
                    {{-- <li class="menu-item {{ request()->routeIs('admin.settings*') ? 'active' : '' }}">
                        <a href="{{ route('admin.settings') }}" class="menu-link">
                            <div>Profile</div>
                        </a>
                    </li> --}}

                    <li class="menu-item {{ request()->routeIs('admin.settings.specialties*') ? 'active' : '' }}">
                        <a href="{{ route('admin.settings.specialties') }}" class="menu-link">
                            <div>Specialty</div>
                        </a>
                    </li>
                </ul>
            </li>
        </ul>
    </aside>

    <div class="menu-mobile-toggler d-xl-none rounded-1">
        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large text-bg-secondary p-2 rounded-1">
            <i class="ti tabler-menu icon-base"></i>
            <i class="ti tabler-chevron-right icon-base"></i>
        </a>
    </div>
    <!-- / Menu -->
</div>
