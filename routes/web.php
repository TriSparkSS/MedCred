<?php

use App\Http\Controllers\CredentialPacketController;
use App\Http\Controllers\ReportExportController;
use App\Livewire\Admin\Analytics\ProductivityDashboardPage;
use App\Livewire\Admin\Credential\CredentialCreatePage;
use App\Livewire\Admin\Credential\CredentialListPage;
use App\Livewire\Admin\DashboardPage;
use App\Livewire\Admin\Documents\DocumentListPage;
use App\Livewire\Admin\Email\EmailDashboardPage;
use App\Livewire\Admin\Imports\BulkImportPage;
use App\Livewire\Admin\Master\BusinessCalendarManager;
use App\Livewire\Admin\Master\CaseTypeManager;
use App\Livewire\Admin\Master\DelayOwnerManager;
use App\Livewire\Admin\Master\DelayRuleManager;
use App\Livewire\Admin\Master\DocumentTypeManager;
use App\Livewire\Admin\Master\FormTemplateManager;
use App\Livewire\Admin\Master\NotificationRuleManager;
use App\Livewire\Admin\Master\NotificationTemplateManager;
use App\Livewire\Admin\Master\PriorityManager;
use App\Livewire\Admin\Master\SlaRuleManager;
use App\Livewire\Admin\Master\StatusManager;
use App\Livewire\Admin\Payer\PayerCreatePage;
use App\Livewire\Admin\Payer\PayerEditPage;
use App\Livewire\Admin\Payer\PayerListPage;
use App\Livewire\Admin\Practices\PracticeCreatePage;
use App\Livewire\Admin\Practices\PracticeDetailsPage;
use App\Livewire\Admin\Practices\PracticeEditPage;
use App\Livewire\Admin\Practices\PracticeListPage;
use App\Livewire\Admin\Provider\ProviderCreatePage;
use App\Livewire\Admin\Provider\ProviderDetailsPage;
use App\Livewire\Admin\Provider\ProviderEditPage;
use App\Livewire\Admin\Provider\ProviderListPage;
use App\Livewire\Admin\ProviderPractice\ProviderPracticeAssignmentPage;
use App\Livewire\Admin\Reports\ReportListPage;
use App\Livewire\Admin\Setting\AdminUserManager;
use App\Livewire\Admin\Setting\SettingPage;
use App\Livewire\Admin\Setting\Specialty\SpecialtyListPage;
use App\Livewire\Admin\Task\TaskBoardPage;
use App\Livewire\Authenticate\LoginPage;
use App\Livewire\Authenticate\PortalLoginPage;
use App\Livewire\Authenticate\ProviderLoginPage;
use App\Livewire\Provider\ProviderActionItemsPage;
use App\Livewire\Provider\ProviderCasesPage;
use App\Livewire\Provider\ProviderDashboardPage;
use App\Livewire\Provider\ProviderDocumentsPage;
use App\Livewire\Practice\PracticeActionItemsPage;
use App\Livewire\Practice\PracticeCasesPage;
use App\Livewire\Practice\PracticeDashboardPage;
use App\Livewire\Practice\PracticeDocumentsPage;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('sp/login', LoginPage::class)->name('login');

Route::get('portal/login', PortalLoginPage::class)->name('portal.login');
Route::get('provider/login', ProviderLoginPage::class)->name('provider.login');
Route::get('practice/login', PortalLoginPage::class)->name('practice.login');

Route::prefix('provider')->name('provider.')->middleware(['provider_auth'])->group(function () {
    Route::get('dashboard', ProviderDashboardPage::class)->middleware('portal.permission:portal.dashboard.view')->name('dashboard');
    Route::get('action-items', ProviderActionItemsPage::class)
        ->middleware('portal.permission:portal.action_items.view')
        ->name('action-items');
    Route::redirect('profile', '/provider/cases')->name('profile');

    Route::get('cases', ProviderCasesPage::class)->middleware('portal.permission:portal.cases.view')->name('cases');
    Route::get('documents', ProviderDocumentsPage::class)->middleware('portal.permission:portal.documents.view')->name('documents');
});

Route::prefix('practice')->name('practice.')->middleware(['practice_auth'])->group(function () {
    Route::get('dashboard', PracticeDashboardPage::class)->middleware('portal.permission:portal.dashboard.view')->name('dashboard');
    Route::get('action-items', PracticeActionItemsPage::class)
        ->middleware('portal.permission:portal.action_items.view')
        ->name('action-items');
    Route::redirect('providers', '/practice/cases')->name('providers');
    Route::redirect('profile', '/practice/cases')->name('profile');

    Route::get('cases', PracticeCasesPage::class)->middleware('portal.permission:portal.cases.view')->name('cases');
    Route::get('documents', PracticeDocumentsPage::class)->middleware('portal.permission:portal.documents.view')->name('documents');
});

Route::prefix('admin')->name('admin.')->middleware(['is_auth:admin'])->group(function () {
    Route::get('dashboard', DashboardPage::class)->middleware('admin.permission:admin.dashboard.view')->name('dashboard');

    Route::get('providers', ProviderListPage::class)->middleware('admin.permission:admin.providers.view')->name('providers');
    Route::get('providers/create', ProviderCreatePage::class)->middleware('admin.permission:admin.providers.create')->name('providers.create');
    Route::get('providers/{provider}/show', ProviderDetailsPage::class)->middleware('admin.permission:admin.providers.view')->name('providers.show');
    Route::get('providers/{provider}/edit', ProviderEditPage::class)->middleware('admin.permission:admin.providers.edit')->name('providers.edit');

    Route::get('practices', PracticeListPage::class)->middleware('admin.permission:admin.practices.view')->name('practices');
    Route::get('practices/create', PracticeCreatePage::class)->middleware('admin.permission:admin.practices.manage')->name('practices.create');
    Route::get('practices/{practice}/show', PracticeDetailsPage::class)->middleware('admin.permission:admin.practices.view')->name('practices.show');
    Route::get('practices/{practice}/edit', PracticeEditPage::class)->middleware('admin.permission:admin.practices.manage')->name('practices.edit');

    Route::get('provider-practices', ProviderPracticeAssignmentPage::class)->middleware('admin.permission:admin.practices.view')->name('provider-practices');

    Route::get('credentials', CredentialListPage::class)->middleware('admin.permission:admin.credentials.view')->name('credentials');
    Route::get('credentials/create', CredentialCreatePage::class)->middleware('admin.permission:admin.credentials.create')->name('credentials.create');
    Route::get('credentials/{case}/packet', [CredentialPacketController::class, 'download'])->middleware('admin.permission:admin.credentials.view')->name('credentials.packet');

    Route::get('emails', EmailDashboardPage::class)->middleware('admin.permission:admin.emails.view')->name('email.dashboard');
    Route::get('documents', DocumentListPage::class)->middleware('admin.permission:admin.documents.view')->name('documents');
    Route::get('tasks/kanban', TaskBoardPage::class)->middleware('admin.permission:admin.tasks.view')->name('tasks.kanban');

    Route::get('settings', SettingPage::class)->middleware('admin.permission:admin.settings.manage')->name('settings');
    Route::get('reports', ReportListPage::class)->middleware('admin.permission:admin.reports.view')->name('reports');
    Route::get('reports/export/{type}', [ReportExportController::class, 'download'])->middleware('admin.permission:admin.reports.export')->name('reports.export');
    Route::get('analytics/productivity', ProductivityDashboardPage::class)->middleware('admin.permission:admin.analytics.view')->name('analytics.productivity');
    Route::get('imports/bulk', BulkImportPage::class)->middleware('admin.permission:admin.imports.manage')->name('imports.bulk');

    Route::get('settings/specialties', SpecialtyListPage::class)->middleware('admin.permission:admin.settings.manage')->name('settings.specialties');
    Route::get('settings/users', AdminUserManager::class)->middleware('admin.permission:admin.users.manage')->name('settings.users');

    Route::get('payers', PayerListPage::class)->middleware('admin.permission:admin.settings.manage')->name('payers');
    Route::get('payers/create', PayerCreatePage::class)->middleware('admin.permission:admin.settings.manage')->name('payers.create');
    Route::get('payers/{payer}/edit', PayerEditPage::class)->middleware('admin.permission:admin.settings.manage')->name('payers.edit');

    Route::prefix('master')->middleware('admin.permission:admin.settings.manage')->group(function () {
        Route::get('statuses', StatusManager::class)->name('master.statuses');
        Route::get('case-types', CaseTypeManager::class)->name('master.case-types');
        Route::get('delay-owners', DelayOwnerManager::class)->name('master.delay-owners');
        Route::get('priorities', PriorityManager::class)->name('master.priorities');
        Route::get('document-types', DocumentTypeManager::class)->name('master.document-types');
        Route::get('email-templates', NotificationTemplateManager::class)->name('master.email-templates');
        Route::get('sla-rules', SlaRuleManager::class)->name('master.sla-rules');
        Route::get('delay-rules', DelayRuleManager::class)->name('master.delay-rules');
        Route::get('business-calendar', BusinessCalendarManager::class)->name('master.business-calendar');
        Route::get('notification-rules', NotificationRuleManager::class)->name('master.notification-rules');
        Route::get('form-templates', FormTemplateManager::class)->name('master.form-templates');
    });
});
