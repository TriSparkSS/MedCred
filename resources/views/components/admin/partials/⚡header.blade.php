<?php

use App\Services\DashboardService;
use App\Services\GlobalSearchService;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public $admin;

    public string $search = '';

    public bool $showSearchResults = false;

    public function mount(): void
    {
        $this->admin = auth()->guard('admin')->user();
    }

    public function updatedSearch(): void
    {
        $this->showSearchResults = strlen(trim($this->search)) >= 2;
    }

    public function clearSearch(): void
    {
        if ($this->search === '' && ! $this->showSearchResults) {
            return;
        }

        $this->search = '';
        $this->showSearchResults = false;
    }

    #[Computed]
    public function notifications(): array
    {
        return app(DashboardService::class)->notificationCounts();
    }

    #[Computed]
    public function notificationTotal(): int
    {
        return app(DashboardService::class)->notificationTotal();
    }

    #[Computed]
    public function searchResults(): array
    {
        if (! $this->showSearchResults) {
            return ['providers' => collect(), 'cases' => collect(), 'payers' => collect()];
        }

        return app(GlobalSearchService::class)->search($this->search);
    }

    #[Computed]
    public function hasSearchResults(): bool
    {
        return app(GlobalSearchService::class)->hasResults($this->searchResults);
    }

    public function logout()
    {
        auth()->guard('admin')->logout();

        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        return $this->redirectRoute('login', navigate: true);
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

    <div class="navbar-nav align-items-center flex-grow-1 me-3 d-none d-md-flex" style="max-width: 480px;">
        <div class="w-100 position-relative">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0"><i class="ti tabler-search"></i></span>
                <input type="search" wire:model.live.debounce.300ms="search"
                    class="form-control border-start-0"
                    placeholder="Search provider, NPI, payer, or application ID..."
                    autocomplete="off"
                    wire:keydown.escape="clearSearch">
                @if ($search)
                    <button type="button" class="btn btn-outline-secondary" wire:click="clearSearch">
                        <i class="ti tabler-x"></i>
                    </button>
                @endif
            </div>

            @if ($showSearchResults)
                <div class="dropdown-menu show w-100 shadow-sm mt-1 p-0" style="max-height: 360px; overflow-y: auto;"
                    wire:click.outside="clearSearch">
                    @if ($this->hasSearchResults)
                        @if ($this->searchResults['providers']->isNotEmpty())
                            <div class="dropdown-header text-uppercase small">Providers</div>
                            @foreach ($this->searchResults['providers'] as $result)
                                <a href="{{ $result['url'] }}" class="dropdown-item py-2">
                                    <div class="fw-medium">{{ $result['label'] }}</div>
                                    @if ($result['meta'])<small class="text-muted">{{ $result['meta'] }}</small>@endif
                                </a>
                            @endforeach
                        @endif
                        @if ($this->searchResults['cases']->isNotEmpty())
                            <div class="dropdown-header text-uppercase small">Applications</div>
                            @foreach ($this->searchResults['cases'] as $result)
                                <a href="{{ $result['url'] }}" class="dropdown-item py-2">
                                    <div class="fw-medium">{{ $result['label'] }}</div>
                                    @if ($result['meta'])<small class="text-muted">{{ $result['meta'] }}</small>@endif
                                </a>
                            @endforeach
                        @endif
                        @if ($this->searchResults['payers']->isNotEmpty())
                            <div class="dropdown-header text-uppercase small">Payers</div>
                            @foreach ($this->searchResults['payers'] as $result)
                                <a href="{{ $result['url'] }}" class="dropdown-item py-2">
                                    <div class="fw-medium">{{ $result['label'] }}</div>
                                </a>
                            @endforeach
                        @endif
                    @else
                        <div class="dropdown-item text-muted small">No results for "{{ $search }}"</div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <div class="navbar-nav-right d-flex align-items-center justify-content-end" id="navbar-collapse">
        <ul class="navbar-nav flex-row align-items-center ms-md-auto">
            <li class="nav-item dropdown me-2">
                <a class="nav-link dropdown-toggle hide-arrow position-relative" href="javascript:void(0);"
                    data-bs-toggle="dropdown">
                    <i class="icon-base ti tabler-bell icon-md"></i>
                    @if ($this->notificationTotal > 0)
                        <span class="badge rounded-pill bg-danger"
                            style="position:absolute; top:0; right:0; font-size:0.65rem;">
                            {{ $this->notificationTotal > 99 ? '99+' : $this->notificationTotal }}
                        </span>
                    @endif
                </a>
                <ul class="dropdown-menu dropdown-menu-end" style="min-width: 280px;">
                    <li class="dropdown-header">Notifications</li>
                    <li>
                        <a class="dropdown-item d-flex justify-content-between" href="{{ route('admin.documents', ['filterExpiry' => 'expiring']) }}">
                            <span><i class="ti tabler-file-alert me-2"></i>Expiring documents</span>
                            <span class="badge bg-label-warning">{{ $this->notifications['expiring_documents'] }}</span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item d-flex justify-content-between" href="{{ route('admin.tasks.kanban') }}">
                            <span><i class="ti tabler-checklist me-2"></i>Overdue tasks</span>
                            <span class="badge bg-label-danger">{{ $this->notifications['overdue_tasks'] }}</span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item d-flex justify-content-between" href="{{ route('admin.credentials', ['category' => 'overdue']) }}">
                            <span><i class="ti tabler-clock-exclamation me-2"></i>Overdue follow-ups</span>
                            <span class="badge bg-label-danger">{{ $this->notifications['overdue_cases'] }}</span>
                        </a>
                    </li>
                </ul>
            </li>

            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="avatar avatar-online">
                        <img src="{{ $admin->avatar ?? '' }}" alt="Admin avatar" class="rounded-circle" />
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="#">
                            <div class="d-flex">
                                <div class="flex-shrink-0 me-3">
                                    <div class="avatar avatar-online">
                                        <img src="{{ $admin->avatar ?? '' }}" alt="Admin avatar"
                                            class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">{{ $admin->name ?? '' }}</h6>
                                    <small class="text-body-secondary">{{ $admin->username ?? '' }}</small>
                                </div>
                            </div>
                        </a>
                    </li>
                    <li><div class="dropdown-divider my-1 mx-n2"></div></li>
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
