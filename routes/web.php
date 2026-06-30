<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Authenticate\LoginPage;
use App\Livewire\Admin\DashboardPage;
use App\Livewire\Admin\Provider\ProviderListPage;
use App\Livewire\Admin\Provider\ProviderCreatePage;
use App\Livewire\Admin\Provider\ProviderDetailsPage;
use App\Livewire\Admin\Practices\PracticeListPage;
use App\Livewire\Admin\Practices\PracticeCreatePage;
use App\Livewire\Admin\Practices\PracticeDetailsPage;
use App\Livewire\Admin\Practices\PracticeEditPage;
use App\Livewire\Admin\Provider\ProviderEditPage;
use App\Livewire\Admin\ProviderPractice\ProviderPracticeAssignmentPage;
use App\Livewire\Admin\Credential\CredentialListPage;
use App\Livewire\Admin\Credential\CredentialCreatePage;
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
use App\Livewire\Admin\Master\NotificationTemplateManager;
use App\Livewire\Admin\Payer\PayerListPage;
use App\Livewire\Admin\Payer\PayerCreatePage;
use App\Livewire\Admin\Payer\PayerEditPage;
use App\Http\Controllers\CredentialPacketController;
use App\Http\Controllers\ReportExportController;
use App\Livewire\Admin\Analytics\ProductivityDashboardPage;
use App\Livewire\Admin\Imports\BulkImportPage;
use App\Livewire\Authenticate\PortalLoginPage;
use App\Livewire\Authenticate\ProviderLoginPage;
use App\Livewire\Provider\ProviderDashboardPage;
use App\Livewire\Provider\ProviderCasesPage;
use App\Livewire\Provider\ProviderDocumentsPage;
use App\Livewire\Provider\ProviderProfilePage;
use App\Livewire\Provider\ProviderActionItemsPage;
use App\Livewire\Practice\PracticeDashboardPage;
use App\Livewire\Practice\PracticeCasesPage;
use App\Livewire\Practice\PracticeDocumentsPage;
use App\Livewire\Practice\PracticeProvidersPage;
use App\Livewire\Practice\PracticeProfilePage;
use App\Livewire\Practice\PracticeActionItemsPage;

Route::get('/', function () {
    return view('welcome');
});

Route::get('sp/login', LoginPage::class)->name('login');

Route::get('portal/login', PortalLoginPage::class)->name('portal.login');
Route::get('provider/login', ProviderLoginPage::class)->name('provider.login');
Route::get('practice/login', PortalLoginPage::class)->name('practice.login');

Route::prefix('provider')->name('provider.')->middleware(['provider_auth'])->group(function () {
    Route::get('dashboard', ProviderDashboardPage::class)->middleware('portal.permission:portal.dashboard.view')->name('dashboard');
    Route::get('cases', ProviderCasesPage::class)->middleware('portal.permission:portal.cases.view')->name('cases');
    Route::get('documents', ProviderDocumentsPage::class)->middleware('portal.permission:portal.documents.view')->name('documents');
    Route::get('action-items', ProviderActionItemsPage::class)->middleware('portal.permission:portal.action_items.view')->name('action-items');
    Route::get('profile', ProviderProfilePage::class)->middleware('portal.permission:portal.profile.view')->name('profile');
});

Route::prefix('practice')->name('practice.')->middleware(['practice_auth'])->group(function () {
    Route::get('dashboard', PracticeDashboardPage::class)->middleware('portal.permission:portal.dashboard.view')->name('dashboard');
    Route::get('cases', PracticeCasesPage::class)->middleware('portal.permission:portal.cases.view')->name('cases');
    Route::get('documents', PracticeDocumentsPage::class)->middleware('portal.permission:portal.documents.view')->name('documents');
    Route::get('action-items', PracticeActionItemsPage::class)->middleware('portal.permission:portal.action_items.view')->name('action-items');
    Route::get('providers', PracticeProvidersPage::class)->middleware('portal.permission:portal.providers.view')->name('providers');
    Route::get('profile', PracticeProfilePage::class)->middleware('portal.permission:portal.profile.view')->name('profile');
});

Route::prefix('admin')->name('admin.')->middleware(['is_auth:admin'])->group(function () {

    Route::get('dashboard', DashboardPage::class)->name('dashboard');
    Route::get('providers', ProviderListPage::class)->name('providers');
    Route::get('providers/create', ProviderCreatePage::class)->name('providers.create');
    Route::get('providers/{provider}/show', ProviderDetailsPage::class)->name('providers.show');
    Route::get('providers/{provider}/edit', ProviderEditPage::class)->name('providers.edit');
    Route::get('practices', PracticeListPage::class)->name('practices');
    Route::get('practices/create', PracticeCreatePage::class)->name('practices.create');
    Route::get('practices/{practice}/show', PracticeDetailsPage::class)->name('practices.show');
    Route::get('practices/{practice}/edit', PracticeEditPage::class)->name('practices.edit');
    Route::get('provider-practices', ProviderPracticeAssignmentPage::class)->name('provider-practices');
    Route::get('credentials', CredentialListPage::class)->name('credentials');
    Route::get('credentials/create', CredentialCreatePage::class)->name('credentials.create');
    Route::get('credentials/{case}/packet', [CredentialPacketController::class, 'download'])->name('credentials.packet');
    Route::get('emails', EmailDashboardPage::class)->name('email.dashboard');
    Route::get('documents', DocumentListPage::class)->name('documents');
    Route::get('tasks/kanban', TaskkanbanPage::class)->name('tasks.kanban');
    Route::get('settings', SettingPage::class)->name('settings');
    Route::get('reports', ReportListPage::class)->name('reports');
    Route::get('reports/export/{type}', [ReportExportController::class, 'download'])->name('reports.export');
    Route::get('analytics/productivity', ProductivityDashboardPage::class)->name('analytics.productivity');
    Route::get('imports/bulk', BulkImportPage::class)->name('imports.bulk');
    Route::get('settings/specialties', SpecialtyListPage::class)->name('settings.specialties');
    Route::get('payers', PayerListPage::class)->name('payers');
    Route::get('payers/create', PayerCreatePage::class)->name('payers.create');
    Route::get('payers/{payer}/edit', PayerEditPage::class)->name('payers.edit');

    // Master Data Management
    Route::prefix('master')->group(function () {
        Route::get('statuses', StatusManager::class)->name('master.statuses');
        Route::get('case-types', CaseTypeManager::class)->name('master.case-types');
        Route::get('delay-owners', DelayOwnerManager::class)->name('master.delay-owners');
        Route::get('priorities', PriorityManager::class)->name('master.priorities');
        Route::get('document-types', DocumentTypeManager::class)->name('master.document-types');
        Route::get('email-templates', NotificationTemplateManager::class)->name('master.email-templates');
    });
});
