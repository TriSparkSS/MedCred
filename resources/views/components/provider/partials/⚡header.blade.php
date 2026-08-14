<?php

use App\Services\PortalActionItemsService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public function logout()
    {
        Auth::guard('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return $this->redirectRoute('portal.login', navigate: true);
    }

    #[Computed]
    public function user()
    {
        return Auth::guard('web')->user();
    }

    #[Computed]
    public function actionItemsCount(): int
    {
        $provider = $this->user?->providerDetails;

        if (! $provider) {
            return 0;
        }

        return app(PortalActionItemsService::class)->providerActionCount($provider);
    }
};
?>

<nav class="layout-navbar container-fluid navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme"
    id="layout-navbar">
    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
        <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
            <i class="icon-base ti tabler-menu-2 icon-md"></i>
        </a>
    </div>

    <div class="navbar-nav-right d-flex align-items-center justify-content-end flex-grow-1" id="navbar-collapse">
        <ul class="navbar-nav flex-row align-items-center ms-md-auto">
            @if(can_do('portal.action_items.view') || can_do('portal.documents.view'))
            <li class="nav-item dropdown me-2">
                <a class="nav-link dropdown-toggle hide-arrow position-relative" href="javascript:void(0);"
                    data-bs-toggle="dropdown">
                    <i class="icon-base ti tabler-bell icon-md"></i>
                    @if ($this->actionItemsCount > 0)
                        <span class="badge rounded-pill bg-danger"
                            style="position:absolute; top:0; right:0; font-size:0.65rem;">
                            {{ $this->actionItemsCount > 99 ? '99+' : $this->actionItemsCount }}
                        </span>
                    @endif
                </a>
                <ul class="dropdown-menu dropdown-menu-end" style="min-width: 260px;">
                    <li class="dropdown-header">Notifications</li>
                    @if(can_do('portal.action_items.view'))
                    <li>
                        <a class="dropdown-item d-flex justify-content-between" href="{{ route('provider.action-items') }}">
                            <span><i class="ti tabler-list-check me-2"></i>Action items</span>
                            <span class="badge bg-label-warning">{{ $this->actionItemsCount }}</span>
                        </a>
                    </li>
                    @endif
                    @if(can_do('portal.documents.view'))
                    <li>
                        <a class="dropdown-item d-flex justify-content-between" href="{{ route('provider.documents') }}">
                            <span><i class="ti tabler-file-alert me-2"></i>Upload center</span>
                        </a>
                    </li>
                    @endif
                </ul>
            </li>
            @endif

            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown">
                    <div class="avatar">
                        <span class="avatar-initial rounded-circle bg-primary text-white">
                            {{ strtoupper(substr($this->user->name ?? 'P', 0, 1)) }}
                        </span>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <div class="dropdown-item">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0 me-3">
                                    <div class="avatar">
                                        <span class="avatar-initial rounded-circle bg-primary text-white">
                                            {{ strtoupper(substr($this->user->name ?? 'P', 0, 1)) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">{{ $this->user->name ?? '' }}</h6>
                                    <small class="text-body-secondary">{{ $this->user->email ?? '' }}</small>
                                </div>
                            </div>
                        </div>
                    </li>
                    <li><div class="dropdown-divider my-1 mx-n2"></div></li>
                    @if(can_do('portal.profile.view'))
                    <li>
                        <a class="dropdown-item" href="{{ route('provider.profile') }}">
                            <i class="icon-base ti tabler-user icon-md me-3"></i>
                            <span>My Profile</span>
                        </a>
                    </li>
                    @endif
                    <li>
                        <a class="dropdown-item" href="#" wire:click.prevent="logout">
                            <i class="icon-base ti tabler-power icon-md me-3"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>
