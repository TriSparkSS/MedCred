<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Authenticate\LoginPage;
use App\Livewire\Admin\DashboardPage;
use App\Livewire\Admin\Provider\ProviderListPage;
use App\Livewire\Admin\Provider\ProviderCreatePage;
use App\Livewire\Admin\Practices\PracticeListPage;
use App\Livewire\Admin\Practices\PracticeCreatePage;
use App\Livewire\Admin\Practices\PracticeDetailsPage;
use App\Livewire\Admin\Practices\PracticeEditPage;
use App\Livewire\Admin\Provider\ProviderEditPage;
use App\Livewire\Admin\Credential\CredentialListPage;
use App\Livewire\Admin\Email\EmailDashboardPage;
use App\Livewire\Admin\Documents\DocumentListPage;
use App\Livewire\Admin\Task\TaskkanbanPage;
use App\Livewire\Admin\Setting\SettingPage;
use App\Livewire\Admin\Reports\ReportListPage;
use App\Livewire\Admin\Setting\Specialty\SpecialtyListPage;
use App\Livewire\Admin\Master\StatusManager;
use App\Livewire\Admin\Master\CaseTypeManager;
use App\Livewire\Admin\Master\DelayOwnerManager;
use App\Livewire\Admin\Master\PriorityManager;
use App\Livewire\Admin\Master\DocumentTypeManager;

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
    Route::get('practices/create', PracticeCreatePage::class)->name('practices.create');
    Route::get('practices/{practice}/show', PracticeDetailsPage::class)->name('practices.show');
    Route::get('practices/{practice}/edit', PracticeEditPage::class)->name('practices.edit');
    Route::get('credentials', CredentialListPage::class)->name('credentials');
    Route::get('emails', EmailDashboardPage::class)->name('email.dashboard');
    Route::get('documents', DocumentListPage::class)->name('documents');
    Route::get('tasks/kanban', TaskkanbanPage::class)->name('tasks.kanban');
    Route::get('settings', SettingPage::class)->name('settings');
    Route::get('reports', ReportListPage::class)->name('reports');
    Route::get('settings/specialties', SpecialtyListPage::class)->name('settings.specialties');

    // Master Data Management
    Route::prefix('master')->group(function () {
        Route::get('statuses', StatusManager::class)->name('master.statuses');
        Route::get('case-types', CaseTypeManager::class)->name('master.case-types');
        Route::get('delay-owners', DelayOwnerManager::class)->name('master.delay-owners');
        Route::get('priorities', PriorityManager::class)->name('master.priorities');
        Route::get('document-types', DocumentTypeManager::class)->name('master.document-types');
    });
});
