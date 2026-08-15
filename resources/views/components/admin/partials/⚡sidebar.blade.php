<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

<div>
    @php
        $adminUser = auth('admin')->user();
        $canAdmin = fn (string $perm) => $adminUser && ($adminUser->roles()->count() === 0 || $adminUser->can($perm));
    @endphp

    <aside id="layout-menu" class="layout-menu menu-vertical menu">
        <div class="app-brand demo ">
            <a href="{{ route('admin.dashboard') }}" class="app-brand-link">
                <span class="app-brand-logo demo">
                    <img src="{{ asset('assets/logo/login-logo.jpeg') }}" height="75" width="200" alt="">
                </span>
            </a>
            <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
                <i class="icon-base ti menu-toggle-icon d-none d-xl-block"></i>
                <i class="icon-base ti tabler-x d-block d-xl-none"></i>
            </a>
        </div>

        <div class="menu-inner-shadow"></div>

        <ul class="menu-inner py-1">
            @if ($canAdmin('admin.dashboard.view'))
            <li class="menu-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <a href="{{ route('admin.dashboard') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-smart-home"></i>
                    <div>Dashboard</div>
                </a>
            </li>
            @endif

            @if ($canAdmin('admin.practices.view'))
            <li class="menu-item {{ request()->routeIs('admin.practices*') || request()->routeIs('admin.provider-practices') ? 'active' : '' }}">
                <a href="{{ route('admin.practices') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-building"></i>
                    <div>Practices</div>
                </a>
            </li>
            @endif

            @if ($canAdmin('admin.providers.view'))
            <li class="menu-item {{ request()->routeIs('admin.providers*') ? 'active' : '' }}">
                <a href="{{ route('admin.providers') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-first-aid-kit"></i>
                    <div>Providers</div>
                </a>
            </li>
            @endif

            @if ($canAdmin('admin.credentials.view'))
            <li class="menu-item {{ request()->routeIs('admin.credentials*') ? 'active' : '' }}">
                <a href="{{ route('admin.credentials') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-clipboard-check"></i>
                    <div>Credentialing Tracker</div>
                </a>
            </li>
            @endif

            @if ($canAdmin('admin.emails.view'))
            <li class="menu-item {{ request()->routeIs('admin.email*') ? 'active' : '' }}">
                <a href="{{ route('admin.email.dashboard') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-mail"></i>
                    <div>Email Center</div>
                </a>
            </li>
            @endif

            @if ($canAdmin('admin.documents.view'))
            <li class="menu-item {{ request()->routeIs('admin.documents') ? 'active' : '' }}">
                <a href="{{ route('admin.documents') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-file-type-doc"></i>
                    <div>Documents</div>
                </a>
            </li>
            @endif

            @if ($canAdmin('admin.tasks.view'))
            <li class="menu-item {{ request()->routeIs('admin.tasks.kanban') ? 'active' : '' }}">
                <a href="{{ route('admin.tasks.kanban') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-list-check"></i>
                    <div>Task & Follow-ups</div>
                </a>
            </li>
            @endif

            @if ($canAdmin('admin.reports.view'))
            <li class="menu-item {{ request()->routeIs('admin.reports*') ? 'active' : '' }}">
                <a href="{{ route('admin.reports') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-chart-bar"></i>
                    <div>Reports</div>
                </a>
            </li>
            @endif

            @if ($canAdmin('admin.analytics.view'))
            <li class="menu-item {{ request()->routeIs('admin.analytics.*') ? 'active' : '' }}">
                <a href="{{ route('admin.analytics.productivity') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-chart-dots"></i>
                    <div>Analytics</div>
                </a>
            </li>
            @endif

            @if ($canAdmin('admin.imports.manage'))
            <li class="menu-item {{ request()->routeIs('admin.imports.*') ? 'active' : '' }}">
                <a href="{{ route('admin.imports.bulk') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-file-import"></i>
                    <div>Bulk Import</div>
                </a>
            </li>
            @endif

            @if ($canAdmin('admin.settings.manage') || $canAdmin('admin.users.manage') || $canAdmin('admin.settings.mail'))
            <li class="menu-item {{ request()->routeIs('admin.settings*') || request()->routeIs('admin.master*') || request()->routeIs('admin.payers*') ? 'active open' : '' }}">
                <a href="javascript:void(0);" class="menu-link menu-toggle">
                    <i class="menu-icon icon-base ti tabler-settings"></i>
                    <div>Admin Settings</div>
                </a>
                <ul class="menu-sub">
                    @if ($canAdmin('admin.settings.manage'))
                    <li class="menu-item {{ request()->routeIs('admin.settings.specialties*') ? 'active' : '' }}">
                        <a href="{{ route('admin.settings.specialties') }}" class="menu-link"><div>Specialty</div></a>
                    </li>
                    @endif
                    @if ($canAdmin('admin.settings.mail'))
                    <li class="menu-item {{ request()->routeIs('admin.settings.mail') ? 'active' : '' }}">
                        <a href="{{ route('admin.settings.mail') }}" class="menu-link"><div>Mail / SMTP</div></a>
                    </li>
                    @endif
                    @if ($canAdmin('admin.settings.manage'))
                    <li class="menu-item {{ request()->routeIs('admin.payers*') ? 'active' : '' }}">
                        <a href="{{ route('admin.payers') }}" class="menu-link"><div>Payers</div></a>
                    </li>
                    <li class="menu-item {{ request()->routeIs('admin.master.statuses') ? 'active' : '' }}">
                        <a href="{{ route('admin.master.statuses') }}" class="menu-link"><div>Statuses</div></a>
                    </li>
                    <li class="menu-item {{ request()->routeIs('admin.master.case-types') ? 'active' : '' }}">
                        <a href="{{ route('admin.master.case-types') }}" class="menu-link"><div>Case Types</div></a>
                    </li>
                    <li class="menu-item {{ request()->routeIs('admin.master.delay-owners') ? 'active' : '' }}">
                        <a href="{{ route('admin.master.delay-owners') }}" class="menu-link"><div>Delay Owners</div></a>
                    </li>
                    <li class="menu-item {{ request()->routeIs('admin.master.delay-rules') ? 'active' : '' }}">
                        <a href="{{ route('admin.master.delay-rules') }}" class="menu-link"><div>Delay Rules</div></a>
                    </li>
                    <li class="menu-item {{ request()->routeIs('admin.master.priorities') ? 'active' : '' }}">
                        <a href="{{ route('admin.master.priorities') }}" class="menu-link"><div>Priorities</div></a>
                    </li>
                    <li class="menu-item {{ request()->routeIs('admin.master.document-types') ? 'active' : '' }}">
                        <a href="{{ route('admin.master.document-types') }}" class="menu-link"><div>Document Types</div></a>
                    </li>
                    <li class="menu-item {{ request()->routeIs('admin.master.email-templates') ? 'active' : '' }}">
                        <a href="{{ route('admin.master.email-templates') }}" class="menu-link"><div>Email Templates</div></a>
                    </li>
                    <li class="menu-item {{ request()->routeIs('admin.master.sla-rules') ? 'active' : '' }}">
                        <a href="{{ route('admin.master.sla-rules') }}" class="menu-link"><div>SLA Rules</div></a>
                    </li>
                    <li class="menu-item {{ request()->routeIs('admin.master.business-calendar') ? 'active' : '' }}">
                        <a href="{{ route('admin.master.business-calendar') }}" class="menu-link"><div>Business Calendar</div></a>
                    </li>
                    <li class="menu-item {{ request()->routeIs('admin.master.notification-rules') ? 'active' : '' }}">
                        <a href="{{ route('admin.master.notification-rules') }}" class="menu-link"><div>Notification Rules</div></a>
                    </li>
                    <li class="menu-item {{ request()->routeIs('admin.master.form-templates') ? 'active' : '' }}">
                        <a href="{{ route('admin.master.form-templates') }}" class="menu-link"><div>Form Templates</div></a>
                    </li>
                    @endif
                    @if ($canAdmin('admin.users.manage'))
                    <li class="menu-item {{ request()->routeIs('admin.settings.users') ? 'active' : '' }}">
                        <a href="{{ route('admin.settings.users') }}" class="menu-link"><div>User Management</div></a>
                    </li>
                    @endif
                </ul>
            </li>
            @endif
        </ul>
    </aside>

    <div class="menu-mobile-toggler d-xl-none rounded-1">
        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large text-bg-secondary p-2 rounded-1">
            <i class="ti tabler-menu icon-base"></i>
            <i class="ti tabler-chevron-right icon-base"></i>
        </a>
    </div>
</div>
