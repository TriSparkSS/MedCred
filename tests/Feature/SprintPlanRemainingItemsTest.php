<?php

use App\Livewire\Admin\Credential\CredentialDetailsPage;
use App\Livewire\Admin\Credential\CredentialListPage;
use App\Livewire\Admin\Provider\ProviderDetailsPage;
use App\Livewire\Admin\Reports\PracticeCredentialingReportPage;
use App\Livewire\Authenticate\ForgotPasswordPage;
use App\Livewire\Authenticate\ResetPasswordPage;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\CaseActivity;
use App\Models\CredentialingCase;
use App\Models\EmailCaseLink;
use App\Models\EmailMessage;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\FollowUpEngine;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(AdminPermissionSeeder::class);
    $this->seed(AdminSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(MasterDataSeeder::class);
});

function createSprintTestPractice(string $name = 'Sprint Plan Practice', string $code = 'SPP'): Practice
{
    $user = User::create([
        'name' => $name.' Owner',
        'email' => strtolower($code).'-'.uniqid().'@example.com',
        'password' => 'password123',
    ]);
    $user->assignRole('practice');

    return Practice::create([
        'user_id' => $user->id,
        'legal_name' => $name,
        'client_code' => $code,
        'email' => strtolower($code).'@example.com',
        'status' => 'active',
    ]);
}

function createSprintTestProvider(string $name, string $npi, Practice $practice): ProviderDetails
{
    $user = User::create([
        'name' => $name,
        'email' => strtolower(str_replace(' ', '.', $name)).'-'.uniqid().'@example.com',
        'password' => 'password123',
    ]);
    $user->assignRole('provider');

    $provider = ProviderDetails::create([
        'user_id' => $user->id,
        'npi' => $npi,
        'status' => 'active',
    ]);

    $provider->practices()->attach($practice->id, ['primary_flag' => true]);

    return $provider;
}

test('S1-01: admin and portal login pages include forgot password links and full password reset flow works with token expiry and audit logging', function () {
    Mail::fake();

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Forgot Password?')
        ->assertSee(route('password.request'), false);

    $this->get(route('portal.login'))
        ->assertOk()
        ->assertSee('Forgot Password?')
        ->assertSee(route('portal.password.request'), false);

    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $admin->update([
        'failed_login_attempts' => 5,
        'locked_until' => now()->addMinutes(15),
    ]);

    // Empty email validation
    Livewire::test(ForgotPasswordPage::class, ['guard' => 'admin'])
        ->set('email', '')
        ->call('sendResetLink')
        ->assertHasErrors(['email']);

    // Request admin password reset link
    $component = Livewire::test(ForgotPasswordPage::class, ['guard' => 'admin'])
        ->set('email', $admin->email)
        ->call('sendResetLink')
        ->assertSet('statusSent', true);

    $plainToken = $component->get('issuedToken');
    expect($plainToken)->not->toBeEmpty();

    $tokenRow = DB::table('password_reset_tokens')
        ->whereRaw('LOWER(email) = ?', ['admin:'.strtolower($admin->email)])
        ->first();
    expect($tokenRow)->not->toBeNull();
    expect(AuditLog::where('action', 'admin.password_reset_requested')->exists())->toBeTrue();

    // Invalid token test
    Livewire::test(ResetPasswordPage::class, ['token' => 'wrong-token', 'guard' => 'admin'])
        ->set('email', $admin->email)
        ->set('password', 'NewStrongPass123!')
        ->set('password_confirmation', 'NewStrongPass123!')
        ->call('resetPassword')
        ->assertHasErrors(['email']);

    // Expired token test (> 60 minutes)
    DB::table('password_reset_tokens')
        ->whereRaw('LOWER(email) = ?', ['admin:'.strtolower($admin->email)])
        ->update(['created_at' => now()->subMinutes(65)]);

    Livewire::test(ResetPasswordPage::class, ['token' => $plainToken, 'guard' => 'admin'])
        ->set('email', $admin->email)
        ->set('password', 'NewStrongPass123!')
        ->set('password_confirmation', 'NewStrongPass123!')
        ->call('resetPassword')
        ->assertHasErrors(['email']);

    // Re-insert valid token and reset password
    DB::table('password_reset_tokens')->updateOrInsert(
        ['email' => 'admin:'.strtolower($admin->email)],
        ['token' => Hash::make($plainToken), 'created_at' => now()]
    );

    Livewire::test(ResetPasswordPage::class, ['token' => $plainToken, 'guard' => 'admin'])
        ->set('email', $admin->email)
        ->set('password', 'NewStrongPass123!')
        ->set('password_confirmation', 'NewStrongPass123!')
        ->call('resetPassword')
        ->assertRedirect(route('login'));

    $admin->refresh();
    expect(Hash::check('NewStrongPass123!', $admin->password))->toBeTrue();
    expect($admin->failed_login_attempts)->toBe(0);
    expect($admin->locked_until)->toBeNull();
    expect(DB::table('password_reset_tokens')->whereRaw('LOWER(email) = ?', ['admin:'.strtolower($admin->email)])->exists())->toBeFalse();
    expect(AuditLog::where('action', 'admin.password_reset_completed')->exists())->toBeTrue();

    // Portal user reset test
    $practice = createSprintTestPractice('Portal Reset Practice', 'PRP');
    $portalUser = $practice->user;
    $portalToken = 'portal-reset-token-987654';

    DB::table('password_reset_tokens')->updateOrInsert(
        ['email' => 'web:'.strtolower($portalUser->email)],
        ['token' => Hash::make($portalToken), 'created_at' => now()]
    );

    Livewire::test(ResetPasswordPage::class, ['token' => $portalToken, 'guard' => 'web'])
        ->set('email', $portalUser->email)
        ->set('password', 'PortalPass456!')
        ->set('password_confirmation', 'PortalPass456!')
        ->call('resetPassword')
        ->assertRedirect(route('portal.login'));

    expect(Hash::check('PortalPass456!', $portalUser->fresh()->password))->toBeTrue();
    expect(AuditLog::where('action', 'portal.password_reset_completed')->exists())->toBeTrue();

    // Guard isolation: portal reset page cannot reset an Admin-only account
    $adminOnlyToken = 'admin-only-token-123456';
    DB::table('password_reset_tokens')->updateOrInsert(
        ['email' => 'admin:'.strtolower($admin->email)],
        ['token' => Hash::make($adminOnlyToken), 'created_at' => now()]
    );

    Livewire::test(ResetPasswordPage::class, ['token' => $adminOnlyToken, 'guard' => 'web'])
        ->set('email', $admin->email)
        ->set('password', 'HackedAdminPass999!')
        ->set('password_confirmation', 'HackedAdminPass999!')
        ->call('resetPassword')
        ->assertHasErrors(['email']);

    expect(Hash::check('NewStrongPass123!', $admin->fresh()->password))->toBeTrue();
});

test('S1-02 & S2-09: top navbar renders Email Center shortcut and Notification Center includes missing documents and revalidation reminders', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = createSprintTestPractice('Notif Practice', 'NFP');
    $provider = createSprintTestProvider('Dr. Notif Provider', '1234509876', $practice);
    $payer = Payer::create(['name' => 'Notif Payer', 'is_active' => true]);
    $docsRequestedStatus = Status::where('name', 'Documents Requested from Provider')->firstOrFail();

    CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $docsRequestedStatus->id,
        'state' => 'FL',
        'revalidation_due_date' => now()->addDays(30)->toDateString(),
    ]);

    $this->actingAs($admin, 'admin');

    $service = app(DashboardService::class);
    $counts = $service->notificationCounts();

    expect($counts)->toHaveKeys([
        'expiring_documents',
        'missing_documents',
        'revalidation_reminders',
        'overdue_tasks',
        'overdue_cases',
        'task_assignments',
    ]);
    expect($counts['missing_documents'])->toBeGreaterThanOrEqual(1);
    expect($counts['revalidation_reminders'])->toBeGreaterThanOrEqual(1);
    expect($service->notificationTotal())->toBe(array_sum($counts));

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(route('admin.email.dashboard'), false)
        ->assertSee('Missing documents')
        ->assertSee('Revalidation reminders');
});

test('S1-04 & S2-08: Provider Profile supports credentialing tab alias, communication tab with linked emails and activity logs, and task creation', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = createSprintTestPractice('Comm Practice', 'CMP');
    $provider = createSprintTestProvider('Dr. Comm Provider', '1998877665', $practice);
    $payer = Payer::create(['name' => 'Comm Payer', 'is_active' => true]);
    $status = Status::where('name', 'At Payer')->firstOrFail();

    $case = CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
        'state' => 'CA',
    ]);

    EmailMessage::create([
        'provider_id' => $provider->id,
        'credentialing_case_id' => $case->id,
        'direction' => 'inbound',
        'from_address' => 'payer-rep@commpayer.com',
        'to_address' => 'cred@revantage.com',
        'subject' => 'Payer Application Status Update',
        'body' => 'Application is under final review.',
        'status' => 'received',
        'received_at' => now(),
    ]);

    EmailCaseLink::create([
        'credentialing_case_id' => $case->id,
        'message_id' => 'msg-link-12345@commpayer.com',
    ]);

    $this->actingAs($admin, 'admin');

    Livewire::test(ProviderDetailsPage::class, ['provider' => $provider])
        ->call('setTab', 'credentialing')
        ->assertSet('activeTab', 'credentialing')
        ->assertSee('Comm Payer')
        ->call('setTab', 'communication')
        ->assertSet('activeTab', 'communication')
        ->assertSee('Payer Application Status Update')
        ->assertSee('msg-link-12345@commpayer.com')
        ->set('commCaseId', (string) $case->id)
        ->set('commType', 'call_log')
        ->set('commSummary', 'Spoke with payer representative regarding effective date.')
        ->call('addCommunicationLog')
        ->assertSee('Spoke with payer representative regarding effective date.')
        ->call('openCreateTaskModal', $case->id)
        ->assertSet('showTaskModal', true)
        ->set('taskForm.title', 'Verify CA License Renewal')
        ->set('taskForm.due_date', now()->addDays(5)->toDateString())
        ->call('saveTask')
        ->assertSet('showTaskModal', false);

    expect(CaseActivity::where('credentialing_case_id', $case->id)
        ->where('activity_type', 'call_log')
        ->where('summary', 'Spoke with payer representative regarding effective date.')
        ->exists())->toBeTrue();

    expect(Task::where('credentialing_case_id', $case->id)
        ->where('provider_id', $provider->id)
        ->where('title', 'Verify CA License Renewal')
        ->exists())->toBeTrue();
});

test('S1-06 & S2-06: Credentialing Tracker displays Last Action column and supports manual task creation alongside Case Detail page', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = createSprintTestPractice('Tracker Task Practice', 'TTP');
    $provider = createSprintTestProvider('Dr. Tracker Provider', '1443322110', $practice);
    $payer = Payer::create(['name' => 'Tracker Payer', 'is_active' => true]);
    $status = Status::where('name', 'At Payer')->firstOrFail();

    $case = CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
        'state' => 'NY',
        'last_action_at' => now()->subHours(3),
    ]);

    $this->actingAs($admin, 'admin');

    // Tracker list page renders Last Action header and creates task
    Livewire::test(CredentialListPage::class)
        ->assertSee('Last Action')
        ->call('openCreateTaskModal', $case->id, $provider->id)
        ->assertSet('showTaskModal', true)
        ->set('taskForm.title', 'Follow up on NY At Payer Case')
        ->set('taskForm.due_date', now()->addDays(2)->toDateString())
        ->call('saveTask')
        ->assertSet('showTaskModal', false);

    expect(Task::where('credentialing_case_id', $case->id)
        ->where('title', 'Follow up on NY At Payer Case')
        ->exists())->toBeTrue();

    // Case Detail header modal creates task
    Livewire::test(CredentialDetailsPage::class, ['case' => $case])
        ->call('openCreateTaskModal')
        ->assertSet('showTaskModal', true)
        ->set('taskForm.title', 'Request updated W-9 from Practice')
        ->set('taskForm.due_date', now()->addDays(4)->toDateString())
        ->call('saveTask')
        ->assertSet('showTaskModal', false);

    expect(Task::where('credentialing_case_id', $case->id)
        ->where('title', 'Request updated W-9 from Practice')
        ->exists())->toBeTrue();
});

test('S2-07: FollowUpEngine auto-creates tasks for resubmission and revalidation and respects automation_paused', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = createSprintTestPractice('Automation Practice', 'AMP');
    $provider = createSprintTestProvider('Dr. Auto Provider', '1776655443', $practice);
    $payer = Payer::create(['name' => 'Automation Payer', 'is_active' => true]);

    $resubStatus = Status::firstOrCreate(
        ['name' => 'Resubmission Required'],
        ['dashboard_category' => 'internal', 'sort_order' => 90, 'is_active' => true]
    );
    $revalStatus = Status::firstOrCreate(
        ['name' => 'Revalidation Due'],
        ['dashboard_category' => 'revalidation', 'sort_order' => 91, 'is_active' => true]
    );
    $approvedStatus = Status::where('name', 'Approved')->firstOrFail();

    $case = CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $approvedStatus->id,
        'assigned_admin_id' => $admin->id,
        'state' => 'TX',
        'automation_paused' => true,
    ]);

    $engine = app(FollowUpEngine::class);

    // When automation_paused is true, no task is created
    $engine->onCaseStatusChanged($case, $approvedStatus->id, $resubStatus->id, $admin->id);
    expect(Task::where('credentialing_case_id', $case->id)->count())->toBe(0);

    // Resume automation and trigger resubmission status
    $case->update(['automation_paused' => false, 'status_id' => $resubStatus->id]);
    $engine->onCaseStatusChanged($case->fresh(), $approvedStatus->id, $resubStatus->id, $admin->id);

    expect(Task::where('credentialing_case_id', $case->id)
        ->where('title', 'like', 'Resubmit application:%')
        ->exists())->toBeTrue();

    // Trigger revalidation status
    $case->update(['status_id' => $revalStatus->id]);
    $engine->onCaseStatusChanged($case->fresh(), $resubStatus->id, $revalStatus->id, $admin->id);

    expect(Task::where('credentialing_case_id', $case->id)
        ->where('title', 'like', 'Revalidation required:%')
        ->exists())->toBeTrue();

    // syncRevalidationTasks creates task for case with upcoming revalidation_due_date
    $caseUpcomingReval = CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $approvedStatus->id,
        'assigned_admin_id' => $admin->id,
        'state' => 'FL',
        'revalidation_due_date' => now()->addDays(25)->toDateString(),
        'automation_paused' => false,
    ]);

    $this->artisan('tasks:sync-expiry-tasks')->assertExitCode(0);
    expect(Task::where('credentialing_case_id', $caseUpcomingReval->id)
        ->where('title', 'like', 'Revalidation required:%')
        ->exists())->toBeTrue();
});

test('S3-08: ReportExportService and ReportExportController export CSV, Excel (.xlsx), and PDF (.pdf) with audit logs', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = createSprintTestPractice('Export Practice', 'EXP');
    $provider = createSprintTestProvider('Dr. Export Provider', '1665544332', $practice);
    $payer = Payer::create(['name' => 'Export Payer', 'is_active' => true]);
    $status = Status::where('name', 'Approved')->firstOrFail();

    CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
        'state' => 'TX',
        'effective_date' => now()->subDays(10)->toDateString(),
    ]);

    $this->actingAs($admin, 'admin');

    // CSV export via route
    $csvResponse = $this->get(route('admin.reports.export', ['type' => 'open_applications', 'format' => 'csv']));
    $csvResponse->assertOk();
    expect($csvResponse->headers->get('content-type'))->toContain('text/csv');

    // XLSX export via route
    $xlsxResponse = $this->get(route('admin.reports.export', ['type' => 'open_applications', 'format' => 'xlsx']));
    $xlsxResponse->assertOk();
    expect($xlsxResponse->headers->get('content-type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $xlsxBinary = $xlsxResponse->streamedContent();
    expect(substr($xlsxBinary, 0, 2))->toBe('PK'); // Valid ZIP/XLSX magic header

    // PDF export via route
    $pdfResponse = $this->get(route('admin.reports.export', ['type' => 'open_applications', 'format' => 'pdf']));
    $pdfResponse->assertOk();
    expect($pdfResponse->headers->get('content-type'))->toContain('application/pdf');
    $pdfBinary = $pdfResponse->streamedContent();
    expect(substr($pdfBinary, 0, 4))->toBe('%PDF'); // Valid PDF magic header

    // PracticeCredentialingReportPage downloadPdf & downloadCsv with audit logging
    $beforeExportAuditCount = AuditLog::where('action', 'report.exported')->count();

    Livewire::test(PracticeCredentialingReportPage::class)
        ->call('downloadPdf')
        ->assertFileDownloaded();

    Livewire::test(PracticeCredentialingReportPage::class)
        ->call('downloadCsv')
        ->assertFileDownloaded();

    expect(AuditLog::where('action', 'report.exported')->count())->toBe($beforeExportAuditCount + 2);
});
