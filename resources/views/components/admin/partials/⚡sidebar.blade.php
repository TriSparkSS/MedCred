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
                {{-- <img src="{{ asset('assets/logo/login-logo.jpeg') }}" class="app-brand-logo" alt="Logo" height="32" width="32"> --}}
                <span class="app-brand-logo demo">
                    <img src="{{ asset('assets/logo/login-logo.jpeg') }}" height="75" width="200" alt=""
                        srcset="">
                </span>
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

            <li class="menu-item {{ request()->routeIs('admin.providers*') ? 'active' : '' }}">
                <a href="{{ route('admin.providers') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-first-aid-kit"></i>
                    <div>Providers</div>
                </a>
            </li>
            <li class="menu-item {{ request()->routeIs('admin.practices*') ? 'active' : '' }}">
                <a href="{{ route('admin.practices') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-building"></i>
                    <div>Practices</div>
                </a>
            </li>
            <li class="menu-item {{ request()->routeIs('admin.provider-practices') ? 'active' : '' }}">
                <a href="{{ route('admin.provider-practices') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-link"></i>
                    <div>Assignments</div>
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
            <li class="menu-item {{ request()->routeIs('admin.analytics.*') ? 'active' : '' }}">
                <a href="{{ route('admin.analytics.productivity') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-chart-dots"></i>
                    <div>Analytics</div>
                </a>
            </li>
            <li class="menu-item {{ request()->routeIs('admin.imports.*') ? 'active' : '' }}">
                <a href="{{ route('admin.imports.bulk') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-file-import"></i>
                    <div>Bulk Import</div>
                </a>
            </li>

            <li
                class="menu-item {{ request()->routeIs('admin.settings*') || request()->routeIs('admin.settings.specialties*') || request()->routeIs('admin.master*') ? 'active open' : '' }}">
                <a href="javascript:void(0);" class="menu-link menu-toggle">
                    <i class="menu-icon icon-base ti tabler-settings"></i>
                    <div>Admin Settings</div>
                </a>

                <ul class="menu-sub">
                    <li class="menu-item {{ request()->routeIs('admin.settings.specialties*') ? 'active' : '' }}">
                        <a href="{{ route('admin.settings.specialties') }}" class="menu-link">
                            <div>Specialty</div>
                        </a>
                    </li>
                    <li class="menu-item {{ request()->routeIs('admin.payers*') ? 'active' : '' }}">
                        <a href="{{ route('admin.payers') }}" class="menu-link">
                            <div>Payers</div>
                        </a>
                    </li>

                    <li class="menu-item {{ request()->routeIs('admin.master.statuses') ? 'active' : '' }}">
                        <a href="{{ route('admin.master.statuses') }}" class="menu-link">
                            <div>Statuses</div>
                        </a>
                    </li>
                    <li class="menu-item {{ request()->routeIs('admin.master.case-types') ? 'active' : '' }}">
                        <a href="{{ route('admin.master.case-types') }}" class="menu-link">
                            <div>Case Types</div>
                        </a>
                    </li>
                    <li class="menu-item {{ request()->routeIs('admin.master.delay-owners') ? 'active' : '' }}">
                        <a href="{{ route('admin.master.delay-owners') }}" class="menu-link">
                            <div>Delay Owners</div>
                        </a>
                    </li>
                    <li class="menu-item {{ request()->routeIs('admin.master.priorities') ? 'active' : '' }}">
                        <a href="{{ route('admin.master.priorities') }}" class="menu-link">
                            <div>Priorities</div>
                        </a>
                    </li>
                    <li class="menu-item {{ request()->routeIs('admin.master.document-types') ? 'active' : '' }}">
                        <a href="{{ route('admin.master.document-types') }}" class="menu-link">
                            <div>Document Types</div>
                        </a>
                    </li>
                    <li class="menu-item {{ request()->routeIs('admin.master.email-templates') ? 'active' : '' }}">
                        <a href="{{ route('admin.master.email-templates') }}" class="menu-link">
                            <div>Email Templates</div>
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
</div>
