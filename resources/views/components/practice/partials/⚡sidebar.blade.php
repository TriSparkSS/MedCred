<?php

use Livewire\Component;

new class extends Component {};
?>

<div>
    <aside id="layout-menu" class="layout-menu menu-vertical menu">
        <div class="app-brand demo">
            <a href="{{ route('practice.dashboard') }}" class="app-brand-link">
                <span class="app-brand-logo demo">
                    <img src="{{ asset('assets/logo/login-logo.jpeg') }}" height="75" width="200" alt="Revantage">
                </span>
            </a>
            <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
                <i class="icon-base ti menu-toggle-icon d-none d-xl-block"></i>
                <i class="icon-base ti tabler-x d-block d-xl-none"></i>
            </a>
        </div>

        <div class="px-4 pb-2">
            <span class="badge bg-label-primary text-uppercase">Practice Portal</span>
        </div>

        <div class="menu-inner-shadow"></div>

        <ul class="menu-inner py-1">
            @if(can_do('portal.dashboard.view'))
            <li class="menu-item {{ request()->routeIs('practice.dashboard') ? 'active' : '' }}">
                <a href="{{ route('practice.dashboard') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-smart-home"></i>
                    <div>Dashboard</div>
                </a>
            </li>
            @endif
            @if(can_do('portal.cases.view'))
            <li class="menu-item {{ request()->routeIs('practice.cases*') ? 'active' : '' }}">
                <a href="{{ route('practice.cases') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-briefcase"></i>
                    <div>Applications</div>
                </a>
            </li>
            @endif
            @if(can_do('portal.action_items.view'))
            <li class="menu-item {{ request()->routeIs('practice.action-items*') ? 'active' : '' }}">
                <a href="{{ route('practice.action-items') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-list-check"></i>
                    <div>Action Items</div>
                </a>
            </li>
            @endif
            @if(can_do('portal.documents.view'))
            <li class="menu-item {{ request()->routeIs('practice.documents*') ? 'active' : '' }}">
                <a href="{{ route('practice.documents') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-upload"></i>
                    <div>Upload Center</div>
                </a>
            </li>
            @endif
            @if(can_do('portal.providers.view'))
            <li class="menu-item {{ request()->routeIs('practice.providers*') ? 'active' : '' }}">
                <a href="{{ route('practice.providers') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-users"></i>
                    <div>Linked Providers</div>
                </a>
            </li>
            @endif
            @if(can_do('portal.profile.view'))
            <li class="menu-item {{ request()->routeIs('practice.profile') ? 'active' : '' }}">
                <a href="{{ route('practice.profile') }}" class="menu-link">
                    <i class="menu-icon icon-base ti tabler-building-hospital"></i>
                    <div>Practice Profile</div>
                </a>
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
