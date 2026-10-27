<?php

use App\Enums\AdminRole;
use App\Livewire\Admin\Credential\CredentialDetailsPage;
use App\Livewire\Admin\Documents\DocumentListPage;
use App\Livewire\Admin\Task\TaskDetailDrawer;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\CaseActivity;
use App\Models\CaseType;
use App\Models\CredentialingCase;
use App\Models\DocumentType;
use App\Models\NotificationTemplate;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\Priority;
use App\Models\ProviderDetails;
use App\Models\Setting;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\FileMalwareScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createWave2SystemAdmin(): Admin
{
    $role = Role::firstOrCreate(['name' => AdminRole::SystemAdmin->value, 'guard_name' => 'admin']);
    foreach ([
        'admin.documents.view',
        'admin.documents.upload',
        'admin.documents.verify',
        'admin.documents.delete',
        'admin.credentials.view',
        'admin.credentials.edit',
        'admin.tasks.view',
        'admin.tasks.manage',
        'admin.emails.send',
    ] as $permName) {
        $perm = Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'admin']);
        $role->givePermissionTo($perm);
    }

    $admin = Admin::create([
        'name' => 'System Admin',
        'username' => 'sysadmin_wave2_'.uniqid(),
        'email' => 'sysadmin_wave2_'.uniqid().'@revantage.test',
        'password' => bcrypt('password'),
        'status' => 'active',
    ]);
    $admin->assignRole($role);

    return $admin;
}

it('blocks EICAR and disguised PHP executable uploads and logs document.malware_blocked (BRD #4)', function () {
    Storage::fake('public');
    $admin = createWave2SystemAdmin();

    $eicarFile = UploadedFile::fake()->createWithContent(
        'infected.pdf',
        FileMalwareScanner::EICAR_SIGNATURE
    );

    expect(fn () => app(DocumentService::class)->upload([
        'title' => 'Malicious File',
    ], $eicarFile, $admin->id))->toThrow(ValidationException::class);

    expect(AuditLog::where('action', 'document.malware_blocked')->exists())->toBeTrue();

    $phpDisguised = UploadedFile::fake()->createWithContent(
        'shell.pdf',
        "<?php system('id'); ?>"
    );

    expect(fn () => app(DocumentService::class)->upload([
        'title' => 'PHP Shell',
    ], $phpDisguised, $admin->id))->toThrow(ValidationException::class);
});

it('encrypts documents at rest and transparently decrypts on download while recording audit logs (BRD #5, #8)', function () {
    Storage::fake('public');
    $admin = createWave2SystemAdmin();

    $plainBytes = '%PDF-1.4 Confidential Provider W-9 Content';
    $file = UploadedFile::fake()->createWithContent('w9.pdf', $plainBytes);

    $document = app(DocumentService::class)->upload([
        'title' => 'Encrypted W-9',
        'encrypt_at_rest' => true,
    ], $file, $admin->id);

    $version = $document->versions()->where('is_current', true)->first();
    expect($version)->not->toBeNull()
        ->and($version->is_encrypted)->toBeTrue();

    $rawStored = Storage::disk('public')->get($version->file_path);
    expect($rawStored)->not->toContain('Confidential Provider W-9 Content');

    $response = $this->actingAs($admin, 'admin')
        ->get(route('admin.documents.download', $document));

    $response->assertOk();
    expect($response->streamedContent())->toBe($plainBytes);

    expect(AuditLog::where('action', 'document.downloaded')
        ->where('auditable_id', $document->id)
        ->exists())->toBeTrue();

    app(DocumentService::class)->delete($document, $admin->id);
    expect(AuditLog::where('action', 'document.deleted')
        ->where('auditable_id', $document->id)
        ->exists())->toBeTrue();
});

it('enforces role-based document visibility for restricted documents (BRD #9)', function () {
    Storage::fake('public');
    $sysAdmin = createWave2SystemAdmin();

    $execRole = Role::firstOrCreate(['name' => AdminRole::CredentialingExecutive->value, 'guard_name' => 'admin']);
    $mgrRole = Role::firstOrCreate(['name' => AdminRole::CredentialingManager->value, 'guard_name' => 'admin']);
    $viewPerm = Permission::firstOrCreate(['name' => 'admin.documents.view', 'guard_name' => 'admin']);
    $execRole->givePermissionTo($viewPerm);
    $mgrRole->givePermissionTo($viewPerm);

    $practiceOwner = User::create([
        'name' => 'Apex Practice User',
        'email' => 'apex_owner@revantage.test',
        'password' => bcrypt('password'),
    ]);

    $practice = Practice::create([
        'user_id' => $practiceOwner->id,
        'legal_name' => 'Apex Health Group',
        'client_code' => 'APEX01',
        'primary_state' => 'TX',
        'onboarding_status' => 'active',
    ]);

    $executive = Admin::create([
        'name' => 'Exec User',
        'username' => 'exec_wave2',
        'email' => 'exec_wave2@revantage.test',
        'password' => bcrypt('password'),
        'status' => 'active',
    ]);
    $executive->assignRole($execRole);
    $executive->practices()->attach($practice->id);

    $manager = Admin::create([
        'name' => 'Manager User',
        'username' => 'mgr_wave2',
        'email' => 'mgr_wave2@revantage.test',
        'password' => bcrypt('password'),
        'status' => 'active',
    ]);
    $manager->assignRole($mgrRole);
    $manager->practices()->attach($practice->id);

    $file = UploadedFile::fake()->createWithContent('contract.pdf', '%PDF-1.4 Restricted');
    $document = app(DocumentService::class)->upload([
        'title' => 'Executive Fee Schedule',
        'practice_id' => $practice->id,
        'visibility' => 'restricted',
        'allowed_roles' => [AdminRole::CredentialingManager->value],
    ], $file, $sysAdmin->id);

    $this->actingAs($executive, 'admin')
        ->get(route('admin.documents.download', $document))
        ->assertForbidden();

    $this->actingAs($manager, 'admin')
        ->get(route('admin.documents.download', $document))
        ->assertOk();
});

it('renders drag-and-drop upload zone and hierarchical folder view by Provider -> Type -> Version (BRD #16, #17)', function () {
    Storage::fake('public');
    $admin = createWave2SystemAdmin();

    $user = User::create([
        'name' => 'Dr. Folder Test',
        'email' => 'folder_provider@revantage.test',
        'password' => bcrypt('password'),
    ]);
    $provider = ProviderDetails::create([
        'user_id' => $user->id,
        'individual_npi' => '1234567890',
    ]);
    $docType = DocumentType::create([
        'name' => 'DEA Certificate',
        'slug' => 'dea-certificate',
        'is_active' => true,
    ]);

    $fileV1 = UploadedFile::fake()->createWithContent('dea_v1.pdf', '%PDF-1.4 v1');
    $doc = app(DocumentService::class)->upload([
        'title' => 'DEA Cert TX',
        'provider_id' => $provider->id,
        'document_type_id' => $docType->id,
    ], $fileV1, $admin->id);

    $fileV2 = UploadedFile::fake()->createWithContent('dea_v2.pdf', '%PDF-1.4 v2');
    $doc->addVersion($fileV2, $admin->id, 'Renewed DEA');

    Livewire::actingAs($admin, 'admin')
        ->test(DocumentListPage::class)
        ->call('openUploadModal')
        ->assertSee('Drag & drop file here, or click to browse')
        ->call('setViewMode', 'folders')
        ->assertSet('viewMode', 'folders')
        ->assertSee('Provider: Dr. Folder Test')
        ->assertSee('DEA Certificate')
        ->assertSee('dea_v1.pdf')
        ->assertSee('dea_v2.pdf');
});

it('logs structured communication entries (Call Log, Fax Log, Portal Check) and toggles case automation pause (BRD #18)', function () {
    $admin = createWave2SystemAdmin();

    $user = User::create([
        'name' => 'Dr. Comm Log',
        'email' => 'commlog@revantage.test',
        'password' => bcrypt('password'),
    ]);
    $practice = Practice::create([
        'user_id' => $user->id,
        'legal_name' => 'Summit Clinic',
        'client_code' => 'SUMM01',
        'primary_state' => 'FL',
        'onboarding_status' => 'active',
    ]);
    $provider = ProviderDetails::create(['user_id' => $user->id]);
    $payer = Payer::create(['name' => 'Aetna', 'slug' => 'aetna', 'is_active' => true]);
    $status = Status::create(['name' => 'Submitted to Payer', 'slug' => 'submitted', 'dashboard_category' => 'payer', 'is_active' => true]);
    $caseType = CaseType::create(['name' => 'Initial', 'slug' => 'initial', 'is_active' => true]);
    $priority = Priority::create(['name' => 'High', 'slug' => 'high']);

    $case = CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
        'case_type_id' => $caseType->id,
        'priority_id' => $priority->id,
        'state' => 'FL',
    ]);

    $nextFollowUp = now()->addDays(5)->toDateString();

    Livewire::actingAs($admin, 'admin')
        ->test(CredentialDetailsPage::class, ['case' => $case])
        ->set('commType', 'call_log')
        ->set('commContactPerson', 'Agent Sarah')
        ->set('commPhoneOrFax', '800-872-3862')
        ->set('commReferenceNumber', 'CALL-99281')
        ->set('commOutcome', 'Application in medical review')
        ->set('commNextFollowUpDate', $nextFollowUp)
        ->set('newNote', 'Spoke with Agent Sarah; review completes next week.')
        ->call('addNote')
        ->assertHasNoErrors()
        ->call('toggleAutomationPause', 'Waiting on manual board letter');

    $activity = CaseActivity::where('credentialing_case_id', $case->id)
        ->where('activity_type', 'call_log')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->reference_number)->toBe('CALL-99281')
        ->and($activity->metadata['contact_person'] ?? null)->toBe('Agent Sarah')
        ->and($activity->metadata['outcome'] ?? null)->toBe('Application in medical review');

    $case->refresh();
    expect($case->next_follow_up_date?->toDateString())->toBe($nextFollowUp)
        ->and($case->automation_paused)->toBeTrue();
});

it('sends email directly from TaskDetailDrawer and logs task note + audit log (BRD #19)', function () {
    Mail::fake();
    $admin = createWave2SystemAdmin();

    Setting::set(\App\Services\MailSettingsService::KEY_PASSWORD, 'smtp-test-secret', true);
    Setting::set(\App\Services\MailSettingsService::KEY_FROM_ADDRESS, 'credentialing@revantage.test');
    Setting::set(\App\Services\MailSettingsService::KEY_FROM_NAME, 'Revantage Credentialing');

    $user = User::create([
        'name' => 'Dr. Task Email',
        'email' => 'dr.task@revantage.test',
        'password' => bcrypt('password'),
    ]);
    $practice = Practice::create([
        'user_id' => $user->id,
        'legal_name' => 'Metro Care',
        'client_code' => 'METR01',
        'primary_state' => 'NY',
        'onboarding_status' => 'active',
    ]);
    $provider = ProviderDetails::create(['user_id' => $user->id]);
    $provider->practices()->attach($practice->id, ['primary_flag' => true]);

    $payer = Payer::create(['name' => 'Cigna', 'slug' => 'cigna', 'is_active' => true]);
    $status = Status::create(['name' => 'New', 'slug' => 'new', 'dashboard_category' => 'intake', 'is_active' => true]);

    $case = CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
        'state' => 'NY',
    ]);

    $task = Task::create([
        'credentialing_case_id' => $case->id,
        'provider_id' => $provider->id,
        'title' => 'Request updated CV from provider',
        'task_type' => 'missing_document',
        'assigned_admin_id' => $admin->id,
        'created_by_admin_id' => $admin->id,
    ]);

    $template = NotificationTemplate::create([
        'name' => 'Task Follow-up Template',
        'template_key' => 'task_followup',
        'subject' => 'Action Required for {{case_number}}',
        'body' => 'Hello {{provider_name}}, please complete {{task_title}}.',
        'category' => 'provider',
        'is_active' => true,
    ]);

    Livewire::actingAs($admin, 'admin')
        ->test(TaskDetailDrawer::class)
        ->call('open', $task->id)
        ->call('toggleEmailForm')
        ->assertSet('showEmailForm', true)
        ->assertSet('emailTo', 'dr.task@revantage.test')
        ->set('emailTemplateId', (string) $template->id)
        ->assertSet('emailSubject', "Action Required for {$case->case_number}")
        ->call('sendTaskEmail')
        ->assertHasNoErrors()
        ->assertSet('showEmailForm', false);

    expect($task->notes()->where('body', 'like', '%Email sent to dr.task@revantage.test%')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'email_sent')->exists())->toBeTrue();
});
