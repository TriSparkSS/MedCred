<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Authenticate\LoginPage;
use App\Livewire\Admin\DashboardPage;
use App\Livewire\Admin\Provider\ProviderListPage;
use App\Livewire\Admin\Provider\ProviderCreatePage;
use App\Livewire\Admin\Practices\PracticeListPage;
use App\Livewire\Admin\Provider\ProviderEditPage;
use App\Livewire\Admin\Credential\CredentialListPage;
use App\Livewire\Admin\Email\EmailDashboardPage;
use App\Livewire\Admin\Documents\DocumentListPage;
use App\Livewire\Admin\Task\TaskkanbanPage;
use App\Livewire\Admin\Setting\SettingPage;
use App\Livewire\Admin\Reports\ReportListPage;
use App\Livewire\Admin\Setting\Specialty\SpecialtyListPage;

Route::get('/', function () {
    return view('welcome');
});

Route::get('sp/login', LoginPage::class)->name('login');


Route::prefix('admin')->name('admin.')->middleware(['is_auth:admin'])->group(function () {

    Route::get('dashboard', DashboardPage::class)->name('dashboard');
    Route::get('providers', ProviderListPage::class)->name('providers');
    Route::get('providers/create', ProviderCreatePage::class)->name('providers.create');
    Route::get('providers/{provider}/edit', ProviderEditPage::class)->name('providers.edit');
    Route::get('practices', PracticeListPage::class)->name('practices');
    Route::get('credentials', CredentialListPage::class)->name('credentials');
    Route::get('emails', EmailDashboardPage::class)->name('email.dashboard');
    Route::get('documents', DocumentListPage::class)->name('documents');
    Route::get('tasks/kanban', TaskkanbanPage::class)->name('tasks.kanban');
    Route::get('settings', SettingPage::class)->name('settings');
    Route::get('reports', ReportListPage::class)->name('reports');

    Route::get('settings/specialties', SpecialtyListPage::class)->name('settings.specialties');
});
