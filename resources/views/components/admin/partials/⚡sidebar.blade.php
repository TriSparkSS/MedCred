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
                <span class="app-brand-logo demo">
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
                </span>
                <span class="app-brand-text demo menu-text fw-bold ms-3">FDB</span>
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
                    <div>{{ __('admin.dashboard') }}</div>
                </a>
            </li>

            <li class="menu-item {{ request()->routeIs('admin.profile') ? 'active' : '' }}">
                <a href="{{ route('admin.profile') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-user"></i>
                    <div>{{ __('admin.profile') }}</div>
                </a>
            </li>

            <li class="menu-item {{ request()->routeIs('admin.cuisines.index') || request()->routeIs('admin.cuisines.create') || request()->routeIs('admin.cuisines.edit') ? 'active' : '' }}">
                <a href="{{ route('admin.cuisines.index') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-tools-kitchen-3"></i>
                    <div>{{ __('admin.cuisines') }}</div>
                </a>
            </li>

            <li class="menu-item {{ request()->routeIs('admin.categories.index') || request()->routeIs('admin.categories.create') || request()->routeIs('admin.categories.edit') ? 'active' : '' }}">
                <a href="{{ route('admin.categories.index') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-tag"></i>
                    <div>{{ __('admin.categories') }}</div>
                </a>
            </li>

            <li class="menu-item {{ request()->routeIs('admin.products.*') ? 'active' : '' }}">
                <a href="{{ route('admin.products.index') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-basket"></i>
                    <div>{{ __('admin.products') }}</div>
                </a>
            </li>

            <li class="menu-item {{ request()->routeIs('admin.sellers.*') ? 'open active' : '' }}">
                <a href="javascript:void(0);" class="menu-link menu-toggle">
                    <i class="menu-icon icon-base ti tabler-building-store"></i>
                    <div>{{ __('admin.sellers') }}</div>
                </a>
                <ul class="menu-sub">
                    <li class="menu-item {{ request()->routeIs('admin.sellers.index') || request()->routeIs('admin.sellers.restaurant') || request()->routeIs('admin.sellers.show') ? 'active' : '' }}">
                        <a href="{{ route('admin.sellers.index') }}" class="menu-link">
                            <div>{{ __('admin.seller_list') }}</div>
                        </a>
                    </li>
                </ul>
            </li>
            <li class="menu-item {{ request()->routeIs('admin.settings.*') ? 'open active' : '' }}">
                <a href="javascript:void(0);" class="menu-link menu-toggle">
                    <i class="menu-icon icon-base ti tabler-settings"></i>
                    <div>{{ __('admin.settings') }}</div>
                </a>
                <ul class="menu-sub">
                    <li class="menu-item {{ request()->routeIs('admin.settings.privacy-terms') ? 'active' : '' }}">
                        <a href="{{ route('admin.settings.privacy-terms') }}" class="menu-link">
                            <div>{{ __('admin.privacy_terms') }}</div>
                        </a>
                    </li>
                    {{-- <li class="menu-item {{ request()->routeIs('admin.settings.file-manager') ? 'active' : '' }}">
                        <a href="{{ route('admin.settings.file-manager') }}" class="menu-link">
                            <div>{{ __('admin.file_manager') }}</div>
                        </a>
                    </li>
                    <li class="menu-item {{ request()->routeIs('admin.settings.didit') ? 'active' : '' }}">
                        <a href="{{ route('admin.settings.didit') }}" class="menu-link">
                            <div>{{ __('admin.didit_settings') }}</div>
                        </a>
                    </li> --}}
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
