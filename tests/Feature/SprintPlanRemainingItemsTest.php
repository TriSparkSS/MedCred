<?php

use App\Data\MailMessageDto;
use App\Livewire\Admin\Credential\CredentialDetailsPage;
use App\Livewire\Admin\Credential\CredentialListPage;
use App\Livewire\Admin\Email\EmailViewPage;
use App\Livewire\Admin\Provider\ProviderDetailsPage;
use App\Livewire\Admin\Reports\PracticeCredentialingReportPage;
use App\Livewire\Admin\Reports\ReportListPage;
use App\Livewire\Authenticate\ForgotPasswordPage;
use App\Livewire\Authenticate\ResetPasswordPage;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\CaseActivity;
use App\Models\CredentialingCase;
use App\Models\DelayOwner;
use App\Models\DelayOwnerHistory;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\EmailAttachment;
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
use App\Services\GraphMailboxService;
use App\Services\ReportExportService;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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

test('S3-04: EmailViewPage saves inbound email attachments into Document Repository with classification, version history, and audit logging', function () {
    Storage::fake('public');

    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = createSprintTestPractice('Attachment Ingest Practice', 'AIP');
    $provider = createSprintTestProvider('Dr. Ingest Provider', '1554433221', $practice);
    $payer = Payer::create(['name' => 'Ingest Payer', 'is_active' => true]);
    $status = Status::where('name', 'Documents Requested from Provider')->firstOrFail();
    $docType = DocumentType::firstOrCreate(
        ['name' => 'State License'],
        ['slug' => 'state-license', 'is_active' => true, 'is_state_specific' => true]
    );

    $case = CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
        'state' => 'TX',
    ]);

    $emailMessage = EmailMessage::create([
        'provider_id' => $provider->id,
        'credentialing_case_id' => $case->id,
        'external_message_id' => 'graph-msg-ingest-1',
        'message_id' => '<ingest-1@example.com>',
        'direction' => 'inbound',
        'from_address' => 'provider@example.com',
        'to_address' => 'credentialing@revantagehbs.com',
        'subject' => 'Attached State License PDF',
        'body' => 'Here is my renewed TX license.',
        'status' => 'received',
        'has_pending_attachments' => true,
        'received_at' => now(),
    ]);

    $emailAttachment = EmailAttachment::create([
        'email_message_id' => $emailMessage->id,
        'original_name' => 'tx_license_2026.pdf',
        'file_path' => '',
        'file_size' => 1024,
        'mime_type' => 'application/pdf',
    ]);

    $encodedMsgId = EmailViewPage::encodeId('graph-msg-ingest-1');
    $encodedAttId = EmailViewPage::encodeId('graph-att-ingest-1');

    $dto = new MailMessageDto(
        id: 'graph-msg-ingest-1',
        folder: 'inbox',
        direction: 'inbound',
        internetMessageId: '<ingest-1@example.com>',
        conversationId: 'conv-ingest-1',
        inReplyTo: null,
        references: null,
        subject: 'Attached State License PDF',
        fromAddress: 'provider@example.com',
        fromName: 'Dr. Ingest Provider',
        toAddress: 'credentialing@revantagehbs.com',
        ccAddress: null,
        bodyHtml: '<p>Here is my renewed TX license.</p>',
        bodyText: 'Here is my renewed TX license.',
        hasAttachments: true,
        attachments: [[
            'id' => 'graph-att-ingest-1',
            'name' => 'tx_license_2026.pdf',
            'contentType' => 'application/pdf',
            'size' => 1024,
            'contentId' => null,
            'isInline' => false,
            'kind' => 'file',
            'sourceUrl' => null,
        ]],
        date: now(),
        isRead: true,
    );

    $this->mock(GraphMailboxService::class, function ($mock) use ($dto) {
        $mock->shouldReceive('normalizeFolder')->andReturn('inbox');
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('getMessage')->andReturn($dto);
        $mock->shouldReceive('getConversation')->andReturn(collect([$dto]));
        $mock->shouldReceive('downloadAttachment')
            ->once()
            ->with('graph-msg-ingest-1', 'graph-att-ingest-1')
            ->andReturn([
                'name' => 'tx_license_2026.pdf',
                'contentType' => 'application/pdf',
                'content' => '%PDF-1.4 Fake PDF content for state license',
            ]);
    });

    $this->actingAs($admin, 'admin');

    Livewire::test(EmailViewPage::class, ['folder' => 'inbox', 'messageId' => $encodedMsgId])
        ->assertSee('Save to Documents')
        ->call('openSaveAttachmentModal', $encodedMsgId, $encodedAttId, 'tx_license_2026.pdf', (string) $case->id)
        ->assertSet('showSaveAttachmentModal', true)
        ->assertSet('ingestCaseId', (string) $case->id)
        ->assertSet('ingestProviderId', (string) $provider->id)
        ->set('ingestDocumentTypeId', (string) $docType->id)
        ->set('ingestTitle', 'TX Medical License 2026')
        ->set('ingestExpiryDate', now()->addYear()->toDateString())
        ->set('ingestNotes', 'Ingested from provider inbound email')
        ->call('saveAttachmentToDocuments')
        ->assertSet('showSaveAttachmentModal', false)
        ->assertHasNoErrors();

    $document = Document::where('credentialing_case_id', $case->id)
        ->where('document_type_id', $docType->id)
        ->first();

    expect($document)->not->toBeNull()
        ->and($document->title)->toBe('TX Medical License 2026')
        ->and($document->provider_id)->toBe($provider->id)
        ->and($document->practice_id)->toBe($practice->id)
        ->and($document->versions()->count())->toBe(1)
        ->and($document->versions()->first()->notes)->toBe('Ingested from provider inbound email');

    expect($emailAttachment->fresh()->document_id)->toBe($document->id);
    expect($emailMessage->fresh()->has_pending_attachments)->toBeFalse();
    expect(AuditLog::where('action', 'email.attachment_ingested')->where('auditable_id', $document->id)->exists())->toBeTrue();
});

test('S3-06 & S3-07: CredentialingCase tracks total age vs current waiting age, and Delay Breakdown supports By Practice dimension and color-coded aging badges', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = createSprintTestPractice('Delay Analytics Practice', 'DAP');
    $provider = createSprintTestProvider('Dr. Delay Provider', '1887766554', $practice);
    $payer = Payer::create(['name' => 'Delay Payer', 'is_active' => true]);
    $status = Status::where('name', 'At Payer')->firstOrFail();
    $payerOwner = DelayOwner::where('name', 'Payer')->firstOrFail();

    $case = CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
        'delay_owner_id' => $payerOwner->id,
        'assigned_admin_id' => $admin->id,
        'state' => 'TX',
        'intake_date' => now()->subDays(65)->toDateString(),
        'submission_date' => now()->subDays(60)->toDateString(),
    ]);

    $history = DelayOwnerHistory::create([
        'credentialing_case_id' => $case->id,
        'delay_owner_id' => $payerOwner->id,
        'source' => 'status_map',
        'reason' => 'Moved to At Payer',
        'changed_by_admin_id' => $admin->id,
    ]);
    DB::table('delay_owner_histories')
        ->where('id', $history->id)
        ->update([
            'created_at' => now()->subDays(12),
            'updated_at' => now()->subDays(12),
        ]);

    $case->refresh();

    expect($case->aging_days)->toBe(60)
        ->and($case->total_age_days)->toBe(60)
        ->and($case->current_waiting_days)->toBe(12);

    $this->actingAs($admin, 'admin');

    // Tracker list page shows both total aging and current waiting days
    Livewire::test(CredentialListPage::class)
        ->assertSee('60d')
        ->assertSee('Wait: 12d');

    // Case details page shows both total aging and current waiting days
    Livewire::test(CredentialDetailsPage::class, ['case' => $case])
        ->assertSee('Waiting: 12 days in current stage');

    // ReportExportService includes by_practice dimension and avg_waiting_days
    $exportService = app(ReportExportService::class);
    $breakdown = $exportService->delayManagementBreakdown();

    expect($breakdown)->toHaveKeys(['total_active', 'by_owner', 'by_team', 'by_provider', 'by_payer', 'by_practice']);

    $practiceRow = collect($breakdown['by_practice'])->firstWhere('label', 'Delay Analytics Practice');
    expect($practiceRow)->not->toBeNull()
        ->and($practiceRow['count'])->toBe(1)
        ->and($practiceRow['avg_aging_days'])->toBe(60)
        ->and($practiceRow['avg_waiting_days'])->toBe(12);

    // ReportListPage supports switching to by_practice tab and renders color-coded badge
    Livewire::test(ReportListPage::class)
        ->assertSee('By Practice')
        ->call('setDelayDimension', 'by_practice')
        ->assertSet('delayDimension', 'by_practice')
        ->assertSee('Delay Analytics Practice')
        ->assertSee('bg-label-danger', false);
});
