<?php

use App\Models\LoginLog;
use App\Support\LocaleManager;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

new class extends Component
{
    public $admin;
    public array $locales = [];
    public string $currentLocale = 'en';

    public function mount()
    {
        $this->admin = auth()->guard('admin')->user();
    }


    public function logout()
    {
        $admin = auth()->guard('admin')->user();

        if ($admin) {
        }

        auth()->guard('admin')->logout();

        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        return $this->redirectRoute('login', navigate: true);
    }
};
?>

<nav class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme"
    id="layout-navbar">
    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
        <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
            <i class="icon-base ti tabler-menu-2 icon-md"></i>
        </a>
    </div>

    <div class="navbar-nav-right d-flex align-items-center justify-content-end" id="navbar-collapse">
        <ul class="navbar-nav flex-row align-items-center ms-md-auto">
            {{-- <li class="nav-item dropdown me-3">
                <a class="nav-link dropdown-toggle hide-arrow px-2" href="javascript:void(0);" data-bs-toggle="dropdown">
                    <i class="icon-base ti tabler-language icon-md"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="dropdown-header">{{ __('admin.language') }}</li>
                    @foreach ($locales as $code => $label)
                        <li>
                            <a class="dropdown-item {{ $currentLocale === $code ? 'active' : '' }}" href="#"
                                wire:click.prevent="setLocale('{{ $code }}')">
                                <span>{{ __('admin.'.match ($code) {
                                    'ru' => 'russian',
                                    'tg' => 'tajik',
                                    default => 'english',
                                }) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </li> --}}
            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown">
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
                                        <img src="{{ $admin->avatar ?? '' }}" alt="Admin avatar" class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">{{ $admin->name ?? '' }}</h6>
                                    <small class="text-body-secondary">{{ $admin->username ?? '' }}</small>
                                </div>
                            </div>
                        </a>
                    </li>
                    <li>
                        <div class="dropdown-divider my-1 mx-n2"></div>
                    </li>
                    <li>
                        <a class="dropdown-item" href="#">
                            <i class="icon-base ti tabler-user icon-md me-3"></i><span>{{ __('admin.my_profile') }}</span>
                        </a>
                    </li>
                    <li>
                        <div class="dropdown-divider my-1 mx-n2"></div>
                    </li>
                    <li>
                        <a class="dropdown-item" href="#" wire:click.prevent="logout">
                            <i class="icon-base ti tabler-power icon-md me-3"></i>
                            <span>{{ __('admin.log_out') }}</span>
                        </a>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>
