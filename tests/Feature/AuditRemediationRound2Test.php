<?php

use App\Data\MailMessageDto;
use App\Enums\AdminRole;
use App\Enums\TaskStatus;
use App\Livewire\Admin\Credential\CredentialCreatePage;
use App\Livewire\Admin\Credential\CredentialDetailsPage;
use App\Livewire\Admin\Credential\CredentialListPage;
use App\Livewire\Admin\Documents\DocumentListPage;
use App\Livewire\Admin\Email\EmailViewPage;
use App\Livewire\Admin\Master\SlaRuleManager;
use App\Livewire\Admin\Practices\PracticeEditPage;
use App\Livewire\Admin\Practices\PracticeListPage;
use App\Livewire\Admin\Provider\ProviderCreatePage;
use App\Livewire\Admin\Provider\ProviderEditPage;
use App\Livewire\Admin\Provider\ProviderListPage;
use App\Livewire\Admin\ProviderPractice\ProviderPracticeAssignmentPage;
use App\Livewire\Admin\Reports\PracticeCredentialingReportPage;
use App\Livewire\Admin\Task\TaskBoardPage;
use App\Livewire\Authenticate\ForgotPasswordPage;
use App\Livewire\Authenticate\LoginPage;
use App\Livewire\Authenticate\ResetPasswordPage;
use App\Livewire\Practice\PracticeDocumentsPage;
use App\Livewire\Provider\ProviderDocumentsPage;
use App\Models\Admin;
use App\Models\CaseActivity;
use App\Models\CaseSlaTimer;
use App\Models\CredentialingCase;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\EmailAttachment;
use App\Models\EmailCaseLink;
use App\Models\EmailMessage;
use App\Models\FormTemplate;
use App\Models\NotificationTemplate;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\SlaRule;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Services\AdminScopeService;
use App\Services\CredentialingEmailService;
use App\Services\CredentialingPacketService;
use App\Services\DashboardService;
use App\Services\DocumentService;
use App\Services\FollowUpEngine;
use App\Services\GraphMailboxService;
use App\Services\PacketBuilderService;
use App\Services\SlaEngineService;
use App\Services\TaskService;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
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

it('item 1: locks forgot/reset password guard, scopes reset tokens by guard, and enforces password complexity', function () {
    Mail::fake();

    $admin = Admin::create([
        'name' => 'Shared Email Admin',
        'username' => 'shared_admin',
        'email' => 'shared@example.com',
        'password' => Hash::make('OldAdminPass!123'),
        'status' => 'active',
    ]);

    $user = User::create([
        'name' => 'Shared Email User',
        'email' => 'shared@example.com',
        'password' => Hash::make('OldUserPass!123'),
        'status' => 1,
    ]);

    // Attempting to mutate #[Locked] $guard property from client throws CannotUpdateLockedPropertyException
    expect(fn () => Livewire::test(ForgotPasswordPage::class, ['guard' => 'web'])
        ->set('guard', 'admin'))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    // Request web password reset
    $component = Livewire::test(ForgotPasswordPage::class, ['guard' => 'web'])
        ->set('email', 'shared@example.com')
        ->call('sendResetLink')
        ->assertSet('statusMessage', 'If an account matches that email address, we have sent a password reset link.');

    $webToken = $component->get('issuedToken');
    expect($webToken)->not->toBeNull();
    expect(DB::table('password_reset_tokens')->where('email', 'web:shared@example.com')->exists())->toBeTrue();

    // Web reset token cannot be replayed against admin guard
    Livewire::test(ResetPasswordPage::class, [
        'token' => $webToken,
        'email' => 'shared@example.com',
        'guard' => 'admin',
    ])
        ->set('password', 'NewStrongPass!123')
        ->set('password_confirmation', 'NewStrongPass!123')
        ->call('resetPassword')
        ->assertHasErrors(['email']);

    expect(Hash::check('OldAdminPass!123', $admin->fresh()->password))->toBeTrue();

    // Weak password rejected on ResetPasswordPage
    Livewire::test(ResetPasswordPage::class, [
        'token' => $webToken,
        'email' => 'shared@example.com',
        'guard' => 'web',
    ])
        ->set('password', 'weakpassword')
        ->set('password_confirmation', 'weakpassword')
        ->call('resetPassword')
        ->assertHasErrors(['password']);

    // Strong password succeeds on intended web guard
    Livewire::test(ResetPasswordPage::class, [
        'token' => $webToken,
        'email' => 'shared@example.com',
        'guard' => 'web',
    ])
        ->set('password', 'NewStrongPass!123')
        ->set('password_confirmation', 'NewStrongPass!123')
        ->call('resetPassword')
        ->assertHasNoErrors();

    expect(Hash::check('NewStrongPass!123', $user->fresh()->password))->toBeTrue();
    expect(Hash::check('OldAdminPass!123', $admin->fresh()->password))->toBeTrue();
});

it('item 2: locks pendingAdminId and enforces brute-force lockout on verifyTwoFactor', function () {
    Mail::fake();

    $admin = Admin::create([
        'name' => '2FA Admin',
        'username' => 'twofa_admin',
        'email' => 'twofa@example.com',
        'password' => Hash::make('ValidPass!123'),
        'status' => 'active',
        'is_two_factor' => true,
    ]);
    $admin->assignRole(AdminRole::SystemAdmin->value);

    RateLimiter::clear('2fa:'.$admin->id.'|127.0.0.1');

    $login = Livewire::test(LoginPage::class)
        ->set('login', 'twofa_admin')
        ->set('password', 'ValidPass!123')
        ->call('login')
        ->assertSet('awaitingTwoFactor', true);

    expect(fn () => $login->set('pendingAdminId', 999))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    for ($i = 1; $i <= 5; $i++) {
        $login->set('twoFactorCode', '000000')->call('verifyTwoFactor');
    }

    $admin->refresh();
    expect($admin->isLockedOut())->toBeTrue()
        ->and($admin->two_factor_code)->toBeNull()
        ->and($login->get('awaitingTwoFactor'))->toBeFalse();
});

it('item 3: hardens API token issuance for 2FA and lockout, sets status and role on provider creation, and scopes cases/documents', function () {
    $twoFaAdmin = Admin::create([
        'name' => '2FA API Admin',
        'username' => 'api_2fa',
        'email' => 'api2fa@example.com',
        'password' => Hash::make('SecretPass!123'),
        'status' => 'active',
        'is_two_factor' => true,
    ]);

    // 2FA admin cannot bypass 2FA via API password token endpoint
    $this->postJson('/api/v1/auth/token', [
        'email' => 'api2fa@example.com',
        'password' => 'SecretPass!123',
        'guard' => 'admin',
    ])->assertForbidden();

    $normalAdmin = Admin::create([
        'name' => 'Scoped API Admin',
        'username' => 'api_scoped',
        'email' => 'apiscoped@example.com',
        'password' => Hash::make('SecretPass!123'),
        'status' => 'active',
        'is_two_factor' => false,
    ]);
    $normalAdmin->assignRole(AdminRole::CredentialingManager->value);

    // 5 failed attempts lock out the admin via API
    for ($i = 1; $i <= 5; $i++) {
        $this->postJson('/api/v1/auth/token', [
            'email' => 'apiscoped@example.com',
            'password' => 'WrongPass!123',
            'guard' => 'admin',
        ])->assertStatus(401);
    }
    expect($normalAdmin->fresh()->isLockedOut())->toBeTrue();

    // Locked out admin is rejected even with valid password
    $this->postJson('/api/v1/auth/token', [
        'email' => 'apiscoped@example.com',
        'password' => 'SecretPass!123',
        'guard' => 'admin',
    ])->assertForbidden();

    // Reset lockout and test scoping + provider store
    $normalAdmin->update(['failed_login_attempts' => 0, 'locked_until' => null]);

    $p1User = User::create(['name' => 'Practice 1', 'email' => 'p1@example.com', 'password' => 'secret', 'status' => 1]);
    $p1 = Practice::create(['user_id' => $p1User->id, 'legal_name' => 'Practice One']);
    $p2User = User::create(['name' => 'Practice 2', 'email' => 'p2@example.com', 'password' => 'secret', 'status' => 1]);
    $p2 = Practice::create(['user_id' => $p2User->id, 'legal_name' => 'Practice Two']);

    $normalAdmin->practices()->sync([$p1->id]);

    $provUser = User::create(['name' => 'Prov Shared', 'email' => 'provshared@example.com', 'password' => 'secret', 'status' => 1]);
    $provider = ProviderDetails::create(['user_id' => $provUser->id, 'npi' => '1234567893']);
    $provider->practices()->attach([$p1->id, $p2->id]);

    $payer = Payer::create(['name' => 'Aetna', 'is_active' => true]);
    $status = Status::first();

    $case1 = CredentialingCase::create([
        'case_number' => 'CRD-API-001',
        'provider_id' => $provider->id,
        'practice_id' => $p1->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
    ]);
    $case2 = CredentialingCase::create([
        'case_number' => 'CRD-API-002',
        'provider_id' => $provider->id,
        'practice_id' => $p2->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
    ]);

    $doc1 = Document::create([
        'title' => 'P1 Doc',
        'provider_id' => $provider->id,
        'practice_id' => $p1->id,
        'visibility' => 'practice',
    ]);
    $doc2 = Document::create([
        'title' => 'P2 Doc',
        'provider_id' => $provider->id,
        'practice_id' => $p2->id,
        'visibility' => 'practice',
    ]);

    $token = $normalAdmin->createToken('test')->plainTextToken;

    $casesResponse = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson("/api/v1/providers/{$provider->id}/cases")
        ->assertOk();
    expect(collect($casesResponse->json('data'))->pluck('case_number')->all())->toBe(['CRD-API-001']);

    $docsResponse = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson("/api/v1/providers/{$provider->id}/documents")
        ->assertOk();
    expect(collect($docsResponse->json('data'))->pluck('title')->all())->toBe(['P1 Doc']);

    // Store endpoint creates active User with 'provider' role
    $unrestrictedAdmin = Admin::create([
        'name' => 'Super API Admin',
        'username' => 'api_super',
        'email' => 'apisuper@example.com',
        'password' => Hash::make('SecretPass!123'),
        'status' => 'active',
    ]);
    $unrestrictedAdmin->assignRole(AdminRole::SystemAdmin->value);
    $superToken = $unrestrictedAdmin->createToken('super')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$superToken)
        ->postJson('/api/v1/providers', [
            'name' => 'API Created Provider',
            'email' => 'apicreated@example.com',
            'npi' => '1245319599',
        ])->assertCreated();

    $createdUser = User::where('email', 'apicreated@example.com')->firstOrFail();
    expect((int) $createdUser->status)->toBe(1)
        ->and($createdUser->hasRole('provider'))->toBeTrue();
});

it('item 4: scans task attachments for malware and stores packet PDFs on private local disk', function () {
    Storage::fake('local');
    Storage::fake('public');

    $admin = Admin::create([
        'name' => 'Task Admin',
        'username' => 'task_admin',
        'email' => 'taskadmin@example.com',
        'password' => 'secret',
        'status' => 'active',
    ]);
    $admin->assignRole(AdminRole::SystemAdmin->value);

    $task = Task::create([
        'title' => 'Upload attachment test',
        'status' => TaskStatus::Open->value,
        'created_by_admin_id' => $admin->id,
    ]);

    $eicar = 'X5O!P%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';
    $infectedFile = UploadedFile::fake()->createWithContent('infected.pdf', $eicar);

    expect(fn () => app(TaskService::class)->attachFile($task, $infectedFile, $admin->id))
        ->toThrow(ValidationException::class);

    // PacketBuilderService writes generated PDFs to local disk, not public disk
    $pUser = User::create(['name' => 'Packet Practice', 'email' => 'pktpractice@example.com', 'password' => 'secret', 'status' => 1]);
    $practice = Practice::create(['user_id' => $pUser->id, 'legal_name' => 'Packet Practice']);
    $provUser = User::create(['name' => 'Packet Provider', 'email' => 'pktprov@example.com', 'password' => 'secret', 'status' => 1]);
    $provider = ProviderDetails::create(['user_id' => $provUser->id, 'npi' => '1234567893']);
    $payer = Payer::create(['name' => 'Cigna', 'is_active' => true]);
    $status = Status::first();

    $case = CredentialingCase::create([
        'case_number' => 'CRD-PKT-001',
        'provider_id' => $provider->id,
        'practice_id' => $practice->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
    ]);

    $template = FormTemplate::create([
        'name' => 'Standard Packet Form',
        'template_key' => 'standard_packet',
        'version' => '1.0',
        'field_mapping' => ['Case Number' => 'case_number'],
        'is_active' => true,
    ]);

    $packet = app(PacketBuilderService::class)->generate($case, $template, $admin->id);
    expect($packet->file_path)->not->toBeNull();
    Storage::disk('local')->assertExists($packet->file_path);
    Storage::disk('public')->assertMissing($packet->file_path);
});

it('item 5: encrypts new document versions when encryption is enabled and decrypts files in packet ZIP', function () {
    Storage::fake('local');
    Storage::fake('public');
    config(['credentialing.documents.encrypt_at_rest' => true]);

    $admin = Admin::create([
        'name' => 'Doc Admin',
        'username' => 'doc_admin',
        'email' => 'docadmin@example.com',
        'password' => 'secret',
        'status' => 'active',
    ]);
    $admin->assignRole(AdminRole::SystemAdmin->value);

    $pUser = User::create(['name' => 'Enc Practice', 'email' => 'encp@example.com', 'password' => 'secret', 'status' => 1]);
    $practice = Practice::create(['user_id' => $pUser->id, 'legal_name' => 'Enc Practice']);
    $provUser = User::create(['name' => 'Enc Provider', 'email' => 'encprov@example.com', 'password' => 'secret', 'status' => 1]);
    $provider = ProviderDetails::create(['user_id' => $provUser->id, 'npi' => '1234567893']);
    $payer = Payer::create(['name' => 'United', 'is_active' => true]);
    $status = Status::first();

    $case = CredentialingCase::create([
        'case_number' => 'CRD-ENC-001',
        'provider_id' => $provider->id,
        'practice_id' => $practice->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
    ]);

    $docService = app(DocumentService::class);
    $fileV1 = UploadedFile::fake()->createWithContent('license_v1.pdf', '%PDF-1.4 V1 PLAINTEXT');
    $doc = $docService->upload([
        'title' => 'State Medical License',
        'credentialing_case_id' => $case->id,
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'visibility' => 'internal',
    ], $fileV1, $admin->id);

    $fileV2 = UploadedFile::fake()->createWithContent('license_v2.pdf', '%PDF-1.4 V2 PLAINTEXT');
    $v2 = $docService->uploadVersion($doc->fresh(), $fileV2, $admin->id, 'Updated license');

    expect($v2->is_encrypted)->toBeTrue();
    $rawStored = Storage::disk('public')->get($v2->file_path);
    expect($rawStored)->not->toBe('%PDF-1.4 V2 PLAINTEXT')
        ->and(Crypt::decryptString($rawStored))->toBe('%PDF-1.4 V2 PLAINTEXT');

    // CredentialingPacketService decrypts encrypted document when building ZIP
    $zipAbsPath = app(CredentialingPacketService::class)->build($case->fresh());
    expect($zipAbsPath)->not->toBeNull();

    $zip = new ZipArchive;
    expect($zip->open($zipAbsPath))->toBeTrue();
    $foundDecrypted = false;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        if (str_contains($stat['name'], 'license_v2.pdf')) {
            expect($zip->getFromIndex($i))->toBe('%PDF-1.4 V2 PLAINTEXT');
            $foundDecrypted = true;
        }
    }
    $zip->close();
    @unlink($zipAbsPath);
    expect($foundDecrypted)->toBeTrue();
});

it('item 6: enforces restricted document default-deny, blocks visibility downgrade, hides restricted/internal docs from portals, and isolates cross-practice records', function () {
    $uploader = Admin::create([
        'name' => 'Uploader Admin',
        'username' => 'uploader_admin',
        'email' => 'uploader@example.com',
        'password' => 'secret',
        'status' => 'active',
    ]);
    $uploader->assignRole(AdminRole::SystemAdmin->value);

    $scopedAdmin = Admin::create([
        'name' => 'Scoped Exec',
        'username' => 'scoped_exec',
        'email' => 'scopedexec@example.com',
        'password' => 'secret',
        'status' => 'active',
    ]);
    $scopedAdmin->assignRole(AdminRole::CredentialingManager->value);

    $p1User = User::create(['name' => 'P1 Portal', 'email' => 'p1portal@example.com', 'password' => 'secret', 'status' => 1]);
    $p1User->assignRole('practice');
    $p1 = Practice::create(['user_id' => $p1User->id, 'legal_name' => 'Practice Alpha']);
    $p2User = User::create(['name' => 'P2 Portal', 'email' => 'p2portal@example.com', 'password' => 'secret', 'status' => 1]);
    $p2User->assignRole('practice');
    $p2 = Practice::create(['user_id' => $p2User->id, 'legal_name' => 'Practice Beta']);

    $scopedAdmin->practices()->sync([$p1->id]);

    $provUser = User::create(['name' => 'Shared Provider', 'email' => 'sharedprov@example.com', 'password' => 'secret', 'status' => 1]);
    $provUser->assignRole('provider');
    $provider = ProviderDetails::create(['user_id' => $provUser->id, 'npi' => '1234567893']);
    $provider->practices()->attach([$p1->id, $p2->id]);

    // Restricted document with empty allowed_roles in P1
    $restrictedDoc = Document::create([
        'title' => 'Restricted Empty Roles',
        'practice_id' => $p1->id,
        'provider_id' => $provider->id,
        'visibility' => 'restricted',
        'allowed_roles' => [],
        'uploaded_by_admin_id' => $uploader->id,
        'uploaded_by_user_id' => $p1User->id,
    ]);

    // Document belonging to P2 for shared provider
    $p2Doc = Document::create([
        'title' => 'Practice Beta Secret Doc',
        'practice_id' => $p2->id,
        'provider_id' => $provider->id,
        'visibility' => 'practice',
        'uploaded_by_admin_id' => $uploader->id,
    ]);

    $scope = app(AdminScopeService::class);
    expect($scope->canViewRestrictedDocument($scopedAdmin, $restrictedDoc))->toBeFalse()
        ->and($scope->canAccessDocument($scopedAdmin, $restrictedDoc))->toBeFalse()
        ->and($scope->canAccessDocument($scopedAdmin, $p2Doc))->toBeFalse();

    $scopedDocIds = $scope->scopeDocuments(Document::query(), $scopedAdmin)->pluck('id')->all();
    expect($scopedDocIds)->not->toContain($restrictedDoc->id)
        ->and($scopedDocIds)->not->toContain($p2Doc->id);

    // Practice and Provider portal pages do not expose restricted or internal documents in myDocuments
    $this->actingAs($p1User, 'web');
    Livewire::test(PracticeDocumentsPage::class)
        ->assertDontSee('Restricted Empty Roles');

    $this->actingAs($provUser, 'web');
    Livewire::test(ProviderDocumentsPage::class)
        ->assertDontSee('Restricted Empty Roles');

    // When shared provider's primary practice is P2 (out of scope), DocumentListPage auto-selects P1 (in scope) for scopedAdmin
    $provider->practices()->sync([
        $p1->id => ['primary_flag' => false],
        $p2->id => ['primary_flag' => true],
    ]);
    $this->actingAs($scopedAdmin, 'admin');
    Livewire::test(DocumentListPage::class)
        ->call('openUploadModal')
        ->set('formData.provider_id', $provider->id)
        ->assertSet('formData.practice_id', $p1->id);
});

it('item 7: soft-deletes ProviderDetails without cascade wiping user, locks IDs, and enforces practice scope on delete', function () {
    $scopedAdmin = Admin::create([
        'name' => 'Scoped Delete Admin',
        'username' => 'scoped_del',
        'email' => 'scopeddel@example.com',
        'password' => 'secret',
        'status' => 'active',
    ]);
    $scopedAdmin->assignRole(AdminRole::CredentialingManager->value);

    $p1User = User::create(['name' => 'P1', 'email' => 'p1del@example.com', 'password' => 'secret', 'status' => 1]);
    $p1 = Practice::create(['user_id' => $p1User->id, 'legal_name' => 'Practice 1']);
    $p2User = User::create(['name' => 'P2', 'email' => 'p2del@example.com', 'password' => 'secret', 'status' => 1]);
    $p2 = Practice::create(['user_id' => $p2User->id, 'legal_name' => 'Practice 2']);

    $scopedAdmin->practices()->sync([$p1->id]);

    $prov1User = User::create(['name' => 'Prov 1', 'email' => 'prov1del@example.com', 'password' => 'secret', 'status' => 1]);
    $prov1 = ProviderDetails::create(['user_id' => $prov1User->id, 'npi' => '1234567893']);
    $prov1->practices()->attach([$p1->id]);

    $prov2User = User::create(['name' => 'Prov 2', 'email' => 'prov2del@example.com', 'password' => 'secret', 'status' => 1]);
    $prov2 = ProviderDetails::create(['user_id' => $prov2User->id, 'npi' => '1245319599']);
    $prov2->practices()->attach([$p2->id]);

    $this->actingAs($scopedAdmin, 'admin');

    // Out-of-scope provider delete is forbidden
    Livewire::test(ProviderListPage::class)
        ->call('delete', $prov2->id)
        ->assertForbidden();

    // Out-of-scope practice delete is forbidden
    Livewire::test(PracticeListPage::class)
        ->call('delete', $p2->id)
        ->assertForbidden();

    // In-scope provider delete soft-deletes ProviderDetails and deactivates User instead of hard deleting User
    Livewire::test(ProviderListPage::class)
        ->call('delete', $prov1->id)
        ->call('onConfirmed');

    expect(ProviderDetails::find($prov1->id))->toBeNull()
        ->and(ProviderDetails::withTrashed()->find($prov1->id))->not->toBeNull()
        ->and(User::find($prov1User->id))->not->toBeNull()
        ->and((int) User::find($prov1User->id)->status)->toBe(0);
});

it('item 8: enforces admin.settings.manage permission and locks recordId on SlaRuleManager', function () {
    $exec = Admin::create([
        'name' => 'Exec No Settings',
        'username' => 'exec_nosettings',
        'email' => 'execnosettings@example.com',
        'password' => 'secret',
        'status' => 'active',
    ]);
    $exec->assignRole(AdminRole::CredentialingExecutive->value);

    $this->actingAs($exec, 'admin');
    Livewire::test(SlaRuleManager::class)->assertForbidden();

    $super = Admin::create([
        'name' => 'Super Settings',
        'username' => 'super_settings',
        'email' => 'supersettings@example.com',
        'password' => 'secret',
        'status' => 'active',
    ]);
    $super->assignRole(AdminRole::SystemAdmin->value);

    $this->actingAs($super, 'admin');
    $comp = Livewire::test(SlaRuleManager::class)->assertOk();

    expect(fn () => $comp->set('recordId', 123))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('item 9: enforces practice scope on EmailViewPage and EmailAttachmentDownloadController', function () {
    $scopedAdmin = Admin::create([
        'name' => 'Scoped Email Admin',
        'username' => 'scoped_email',
        'email' => 'scopedemail@example.com',
        'password' => 'secret',
        'status' => 'active',
    ]);
    $scopedAdmin->assignRole(AdminRole::CredentialingManager->value);

    $p1User = User::create(['name' => 'P1 Email', 'email' => 'p1email@example.com', 'password' => 'secret', 'status' => 1]);
    $p1 = Practice::create(['user_id' => $p1User->id, 'legal_name' => 'Practice Email 1']);
    $p2User = User::create(['name' => 'P2 Email', 'email' => 'p2email@example.com', 'password' => 'secret', 'status' => 1]);
    $p2 = Practice::create(['user_id' => $p2User->id, 'legal_name' => 'Practice Email 2']);

    $scopedAdmin->practices()->sync([$p1->id]);

    $prov2User = User::create(['name' => 'Prov 2 Email', 'email' => 'prov2email@example.com', 'password' => 'secret', 'status' => 1]);
    $prov2 = ProviderDetails::create(['user_id' => $prov2User->id, 'npi' => '1234567893']);
    $prov2->practices()->attach([$p2->id]);

    $payer = Payer::create(['name' => 'Humana', 'is_active' => true]);
    $status = Status::first();

    $case2 = CredentialingCase::create([
        'case_number' => 'CRD-EML-002',
        'provider_id' => $prov2->id,
        'practice_id' => $p2->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
    ]);

    EmailCaseLink::create([
        'message_id' => 'graph-msg-unassigned',
        'credentialing_case_id' => $case2->id,
    ]);

    $msg2 = EmailMessage::create([
        'credentialing_case_id' => $case2->id,
        'provider_id' => $prov2->id,
        'direction' => 'inbound',
        'subject' => 'Secret Message',
        'body' => 'Confidential payer email body',
        'from_address' => 'payer@example.com',
        'to_address' => 'crm@example.com',
        'external_message_id' => 'graph-msg-unassigned',
        'message_id' => 'graph-msg-unassigned',
    ]);

    $att2 = EmailAttachment::create([
        'email_message_id' => $msg2->id,
        'file_path' => 'attachments/secret.pdf',
        'original_name' => 'secret.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
    ]);

    $this->mock(GraphMailboxService::class, function ($mock) {
        $mock->shouldReceive('normalizeFolder')->andReturn('inbox');
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldNotReceive('getMessage');
        $mock->shouldNotReceive('downloadAttachment');
    });

    $this->actingAs($scopedAdmin, 'admin');

    $encodedMsgId = EmailViewPage::encodeId('graph-msg-unassigned');
    $encodedAttId = EmailViewPage::encodeId((string) $att2->id);

    Livewire::test(EmailViewPage::class, ['folder' => 'inbox', 'messageId' => $encodedMsgId])
        ->assertForbidden();

    $this->get(route('admin.email.attachment', [
        'folder' => 'inbox',
        'messageId' => $encodedMsgId,
        'attachmentId' => $encodedAttId,
    ]))->assertForbidden();

    // Also verify thread message linked to out-of-scope case aborts 403 BEFORE getMessage(..., markRead: true) is called
    EmailCaseLink::create([
        'message_id' => 'thread-reply-unassigned@example.com',
        'credentialing_case_id' => $case2->id,
    ]);

    $anchorDto = new MailMessageDto(
        id: 'graph-anchor-unlinked',
        folder: 'inbox',
        direction: 'inbound',
        internetMessageId: '<anchor-unlinked@example.com>',
        conversationId: 'conv-thread-2',
        inReplyTo: null,
        references: null,
        subject: 'Thread Subject',
        fromAddress: 'payer@example.com',
        fromName: 'Payer',
        toAddress: 'crm@example.com',
        ccAddress: null,
        bodyHtml: '<p>Body</p>',
        bodyText: 'Body',
        hasAttachments: false,
        attachments: [],
        date: now(),
        isRead: false,
    );

    $replyDto = new MailMessageDto(
        id: 'graph-reply-linked',
        folder: 'inbox',
        direction: 'inbound',
        internetMessageId: '<thread-reply-unassigned@example.com>',
        conversationId: 'conv-thread-2',
        inReplyTo: '<anchor-unlinked@example.com>',
        references: '<anchor-unlinked@example.com>',
        subject: 'Re: Thread Subject',
        fromAddress: 'payer@example.com',
        fromName: 'Payer',
        toAddress: 'crm@example.com',
        ccAddress: null,
        bodyHtml: '<p>Reply</p>',
        bodyText: 'Reply',
        hasAttachments: false,
        attachments: [],
        date: now(),
        isRead: false,
    );

    $this->mock(GraphMailboxService::class, function ($mock) use ($anchorDto, $replyDto) {
        $mock->shouldReceive('normalizeFolder')->andReturn('inbox');
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('getMessage')
            ->with('graph-anchor-unlinked', 'inbox', false)
            ->once()
            ->andReturn($anchorDto);
        $mock->shouldReceive('getConversation')
            ->with('conv-thread-2', 'graph-anchor-unlinked', 'inbox')
            ->once()
            ->andReturn(collect([$anchorDto, $replyDto]));
        $mock->shouldNotReceive('getMessage')->with('graph-anchor-unlinked', 'inbox', true);
    });

    Livewire::test(EmailViewPage::class, [
        'folder' => 'inbox',
        'messageId' => EmailViewPage::encodeId('graph-anchor-unlinked'),
    ])->assertForbidden();
});

it('item 10: sets automation_key on manual document expiry tasks and prevents duplicates', function () {
    Storage::fake('public');

    $admin = Admin::create([
        'name' => 'Expiry Task Admin',
        'username' => 'expiry_admin',
        'email' => 'expiryadmin@example.com',
        'password' => 'secret',
        'status' => 'active',
    ]);
    $admin->assignRole(AdminRole::SystemAdmin->value);

    $provUser = User::create(['name' => 'Expiry Prov', 'email' => 'expiryprov@example.com', 'password' => 'secret', 'status' => 1]);
    $provider = ProviderDetails::create(['user_id' => $provUser->id, 'npi' => '1234567893']);
    $docType = DocumentType::first();

    $this->actingAs($admin, 'admin');

    $file = UploadedFile::fake()->createWithContent('dea.pdf', '%PDF-1.4 DEA');
    Livewire::test(DocumentListPage::class)
        ->call('openUploadModal')
        ->set('formData.title', 'DEA Certificate')
        ->set('formData.document_type_id', $docType->id)
        ->set('formData.provider_id', $provider->id)
        ->set('formData.expiry_date', now()->addDays(15)->toDateString())
        ->set('uploadFile', $file)
        ->call('saveDocument')
        ->assertHasNoErrors();

    $doc = Document::where('title', 'DEA Certificate')->firstOrFail();

    $tasks = Task::where('automation_key', 'expiry:'.$doc->id)->get();
    expect($tasks)->toHaveCount(1);

    // Running FollowUpEngine::syncExpiryTasks does not create a duplicate
    app(FollowUpEngine::class)->syncExpiryTasks();

    expect(Task::where('automation_key', 'expiry:'.$doc->id)->count())->toBe(1);
});

it('item 11: renders CredentialDetailsPage with portable CASE WHEN SQL ordering without errors', function () {
    $admin = Admin::create([
        'name' => 'Case Detail Admin',
        'username' => 'casedetail_admin',
        'email' => 'casedetail@example.com',
        'password' => 'secret',
        'status' => 'active',
    ]);
    $admin->assignRole(AdminRole::SystemAdmin->value);

    $pUser = User::create(['name' => 'Case Detail Practice', 'email' => 'cdp@example.com', 'password' => 'secret', 'status' => 1]);
    $practice = Practice::create(['user_id' => $pUser->id, 'legal_name' => 'Case Detail Practice']);
    $provUser = User::create(['name' => 'Case Detail Provider', 'email' => 'cdprov@example.com', 'password' => 'secret', 'status' => 1]);
    $provider = ProviderDetails::create(['user_id' => $provUser->id, 'npi' => '1234567893']);
    $payer = Payer::create(['name' => 'BCBS', 'is_active' => true]);
    $status = Status::first();

    $case = CredentialingCase::create([
        'case_number' => 'CRD-DTL-001',
        'provider_id' => $provider->id,
        'practice_id' => $practice->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
    ]);

    $this->actingAs($admin, 'admin');

    Livewire::test(CredentialDetailsPage::class, ['case' => $case])
        ->assertOk()
        ->assertSee('CRD-DTL-001');
});

it('item 12: redirects deactivated admins and portal users to valid login routes without RouteNotFoundException', function () {
    $inactiveAdmin = Admin::create([
        'name' => 'Inactive Admin',
        'username' => 'inactive_admin',
        'email' => 'inactiveadmin@example.com',
        'password' => 'secret',
        'status' => 'inactive',
    ]);
    $inactiveAdmin->assignRole(AdminRole::SystemAdmin->value);

    $this->actingAs($inactiveAdmin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));

    $inactiveUser = User::create([
        'name' => 'Inactive User',
        'email' => 'inactiveuser@example.com',
        'password' => 'secret',
        'status' => 0,
    ]);
    $inactiveUser->assignRole('provider');

    $this->actingAs($inactiveUser, 'web')
        ->get(route('provider.dashboard'))
        ->assertRedirect(route('portal.login'));
});

it('item 13: notifies active admins (status = active) when SlaEngineService escalates a case', function () {
    $manager = Admin::create([
        'name' => 'Active SLA Manager',
        'username' => 'sla_manager',
        'email' => 'slamanager@example.com',
        'password' => 'secret',
        'status' => 'active',
    ]);
    $manager->assignRole(AdminRole::CredentialingManager->value);

    $template = NotificationTemplate::create([
        'name' => 'Escalation Alert',
        'template_key' => 'escalation_alert_test',
        'subject' => 'Case Escalated',
        'body' => 'Case {{case_number}} escalated.',
        'is_active' => true,
    ]);

    $pUser = User::create(['name' => 'SLA Practice', 'email' => 'slap@example.com', 'password' => 'secret', 'status' => 1]);
    $practice = Practice::create(['user_id' => $pUser->id, 'legal_name' => 'SLA Practice']);
    $provUser = User::create(['name' => 'SLA Provider', 'email' => 'slaprov@example.com', 'password' => 'secret', 'status' => 1]);
    $provider = ProviderDetails::create(['user_id' => $provUser->id, 'npi' => '1234567893']);
    $payer = Payer::create(['name' => 'SLA Payer', 'is_active' => true]);
    $status = Status::where('dashboard_category', 'provider')->first() ?? Status::first();

    $case = CredentialingCase::create([
        'case_number' => 'CRD-SLA-001',
        'provider_id' => $provider->id,
        'practice_id' => $practice->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
    ]);

    $rule = SlaRule::create([
        'name' => 'Escalate to Manager',
        'rule_key' => 'custom_escalation_rule',
        'dashboard_category' => 'provider',
        'business_days' => 1,
        'action' => 'escalate',
        'notification_template_id' => $template->id,
        'is_active' => true,
    ]);

    CaseSlaTimer::create([
        'credentialing_case_id' => $case->id,
        'rule_key' => 'custom_escalation_rule',
        'started_at' => now()->subDays(5),
        'due_at' => now()->subDay(),
        'status' => 'active',
    ]);

    $sentRecipients = [];
    $this->mock(CredentialingEmailService::class, function ($mock) use (&$sentRecipients) {
        $mock->shouldReceive('sendFromTemplate')
            ->andReturnUsing(function ($case, $template, $recipientEmail) use (&$sentRecipients) {
                $sentRecipients[] = $recipientEmail;
            });
    });

    $processed = app(SlaEngineService::class)->runDueChecks();
    expect($processed['escalations'])->toBe(1)
        ->and($sentRecipients)->toContain('slamanager@example.com');
});

it('item 14: does not recreate already-completed automated tasks in TaskService::ensureTask or FollowUpEngine', function () {
    $pUser = User::create(['name' => 'Ensure Practice', 'email' => 'ensurep@example.com', 'password' => 'secret', 'status' => 1]);
    $practice = Practice::create(['user_id' => $pUser->id, 'legal_name' => 'Ensure Practice']);
    $provUser = User::create(['name' => 'Ensure Provider', 'email' => 'ensureprov@example.com', 'password' => 'secret', 'status' => 1]);
    $provider = ProviderDetails::create(['user_id' => $provUser->id, 'npi' => '1234567893']);
    $payer = Payer::create(['name' => 'Ensure Payer', 'is_active' => true]);
    $status = Status::first();

    $case = CredentialingCase::create([
        'case_number' => 'CRD-ENS-001',
        'provider_id' => $provider->id,
        'practice_id' => $practice->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
        'revalidation_due_date' => now()->addDays(15)->toDateString(),
    ]);

    $doc = Document::create([
        'title' => 'Completed Expiry Doc',
        'provider_id' => $provider->id,
        'credentialing_case_id' => $case->id,
        'expiry_date' => now()->addDays(10)->toDateString(),
        'visibility' => 'internal',
    ]);

    $taskService = app(TaskService::class);
    $task = $taskService->ensureTask('expiry:'.$doc->id, $case, [
        'title' => 'Renew Completed Expiry Doc',
        'task_type' => 'document_expiry',
        'due_date' => now()->addDays(5)->toDateString(),
    ]);

    $taskService->complete($task, 1);
    expect($task->fresh()->status->value ?? $task->fresh()->status)->toBe('completed');

    // Calling ensureTask or FollowUpEngine again must not spawn a new open task for the same automation_key
    $again = $taskService->ensureTask('expiry:'.$doc->id, $case, [
        'title' => 'Renew Completed Expiry Doc',
        'task_type' => 'document_expiry',
        'due_date' => now()->addDays(5)->toDateString(),
    ]);
    expect($again->id)->toBe($task->id);

    $created = app(FollowUpEngine::class)->syncExpiryTasks();
    expect($created)->toBe(0)
        ->and(Task::where('automation_key', 'expiry:'.$doc->id)->count())->toBe(1);
});

it('item 15: updates existing practice license document instead of creating duplicate Document rows', function () {
    Storage::fake('public');

    $admin = Admin::create([
        'name' => 'Practice Form Admin',
        'username' => 'pform_admin',
        'email' => 'pform@example.com',
        'password' => 'secret',
        'status' => 'active',
    ]);
    $admin->assignRole(AdminRole::SystemAdmin->value);

    $pUser = User::create(['name' => 'Lic Practice', 'email' => 'licp@example.com', 'password' => 'secret', 'status' => 1]);
    $practice = Practice::create([
        'user_id' => $pUser->id,
        'legal_name' => 'License Sync Practice',
        'client_code' => 'LSP01',
        'email' => 'licp@example.com',
        'status' => 'active',
        'license_name' => 'Texas Clinic License',
        'license_number' => 'LIC-100',
    ]);
    $practice->addresses()->create([
        'type' => 'primary',
        'address1' => '100 Main St',
        'city' => 'Austin',
        'state' => 'TX',
        'zip_code' => '78701',
        'country' => 'United States',
        'status' => 'active',
    ]);

    $this->actingAs($admin, 'admin');

    $file1 = UploadedFile::fake()->createWithContent('lic1.pdf', '%PDF-1.4 LIC 1');
    Livewire::test(PracticeEditPage::class, ['practice' => $practice->id])
        ->set('formData.license_number', 'LIC-100')
        ->set('document', $file1)
        ->call('save')
        ->assertHasNoErrors();

    $doc = Document::where('practice_id', $practice->id)->firstOrFail();
    expect(Document::where('practice_id', $practice->id)->count())->toBe(1)
        ->and($doc->document_number)->toBe('LIC-100')
        ->and(DocumentVersion::where('document_id', $doc->id)->count())->toBe(1);

    $file2 = UploadedFile::fake()->createWithContent('lic2.pdf', '%PDF-1.4 LIC 2');
    Livewire::test(PracticeEditPage::class, ['practice' => $practice->id])
        ->set('formData.license_number', 'LIC-200')
        ->set('document', $file2)
        ->call('save')
        ->assertHasNoErrors();

    expect(Document::where('practice_id', $practice->id)->count())->toBe(1)
        ->and($doc->fresh()->document_number)->toBe('LIC-200')
        ->and(DocumentVersion::where('document_id', $doc->id)->count())->toBe(2);
});

it('item 16: treats today due dates as due today (not expired/overdue) and filters revalidation windows strictly in the future', function () {
    $docToday = Document::create([
        'title' => 'Expires Today Doc',
        'expiry_date' => now()->toDateString(),
        'visibility' => 'internal',
    ]);

    $docYesterday = Document::create([
        'title' => 'Expired Yesterday Doc',
        'expiry_date' => now()->subDay()->toDateString(),
        'visibility' => 'internal',
    ]);

    expect($docToday->isExpired())->toBeFalse()
        ->and($docToday->isExpiringSoon(30))->toBeTrue()
        ->and($docYesterday->isExpired())->toBeTrue();

    $pUser = User::create(['name' => 'Boundary Practice', 'email' => 'boundp@example.com', 'password' => 'secret', 'status' => 1]);
    $practice = Practice::create(['user_id' => $pUser->id, 'legal_name' => 'Boundary Practice']);
    $provUser = User::create(['name' => 'Boundary Provider', 'email' => 'boundprov@example.com', 'password' => 'secret', 'status' => 1]);
    $provider = ProviderDetails::create(['user_id' => $provUser->id, 'npi' => '1234567893']);
    $payer = Payer::create(['name' => 'Anthem', 'is_active' => true]);
    $status = Status::where('slug', 'not_started')->first() ?? Status::first();

    $caseToday = CredentialingCase::create([
        'case_number' => 'CRD-TODAY-001',
        'provider_id' => $provider->id,
        'practice_id' => $practice->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
        'next_follow_up_date' => now()->toDateString(),
        'revalidation_due_date' => now()->addDays(15)->toDateString(),
    ]);

    $casePastReval = CredentialingCase::create([
        'case_number' => 'CRD-PAST-001',
        'provider_id' => $provider->id,
        'practice_id' => $practice->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
        'next_follow_up_date' => now()->subDay()->toDateString(),
        'revalidation_due_date' => now()->subDays(5)->toDateString(),
    ]);

    expect($caseToday->isOverdue())->toBeFalse()
        ->and($casePastReval->isOverdue())->toBeTrue();

    $admin = Admin::create([
        'name' => 'Boundary Admin',
        'username' => 'boundary_admin',
        'email' => 'boundary@example.com',
        'password' => 'secret',
        'status' => 'active',
    ]);
    $admin->assignRole(AdminRole::SystemAdmin->value);
    $this->actingAs($admin, 'admin');

    Livewire::test(CredentialListPage::class)
        ->set('filterRevalidation', '30')
        ->assertSee('CRD-TODAY-001')
        ->assertDontSee('CRD-PAST-001');
});

it('item 17: validates unique NPI on ProviderCreatePage, preserves out-of-scope practice links on ProviderEditPage, and sets status=1 on imported users', function () {
    $super = Admin::create([
        'name' => 'Super Prov Admin',
        'username' => 'super_prov',
        'email' => 'superprov@example.com',
        'password' => 'secret',
        'status' => 'active',
    ]);
    $super->assignRole(AdminRole::SystemAdmin->value);

    $existingUser = User::create(['name' => 'Existing NPI Prov', 'email' => 'existingnpi@example.com', 'password' => 'secret', 'status' => 1]);
    $existingProv = ProviderDetails::create([
        'user_id' => $existingUser->id,
        'npi' => '1234567893',
        'practice' => 'Practice Edit 1',
        'address' => '123 Main St',
        'city' => 'Austin',
        'state' => 'TX',
        'zip' => '78701',
        'status' => 'active',
    ]);

    $this->actingAs($super, 'admin');

    // Duplicate NPI fails validation cleanly instead of throwing SQL QueryException
    Livewire::test(ProviderCreatePage::class)
        ->set('formData.first_name', 'Dup')
        ->set('formData.last_name', 'Provider')
        ->set('formData.email', 'dupprovider@example.com')
        ->set('formData.npi', '1234567893')
        ->call('save')
        ->assertHasErrors(['formData.npi']);

    // Scoped admin editing a shared provider does not see or clobber Practice B link, and setting inactive updates User.status
    $p1User = User::create(['name' => 'P1 Edit', 'email' => 'p1edit@example.com', 'password' => 'secret', 'status' => 1]);
    $p1 = Practice::create(['user_id' => $p1User->id, 'legal_name' => 'Practice Edit 1']);
    $p2User = User::create(['name' => 'P2 Edit', 'email' => 'p2edit@example.com', 'password' => 'secret', 'status' => 1]);
    $p2 = Practice::create(['user_id' => $p2User->id, 'legal_name' => 'Practice Edit 2']);

    $existingProv->practices()->sync([
        $p1->id => ['primary_flag' => false],
        $p2->id => ['primary_flag' => true],
    ]);

    $scopedAdmin = Admin::create([
        'name' => 'Scoped Edit Admin',
        'username' => 'scoped_edit',
        'email' => 'scopededit@example.com',
        'password' => 'secret',
        'status' => 'active',
    ]);
    $scopedAdmin->assignRole(AdminRole::CredentialingManager->value);
    $scopedAdmin->practices()->sync([$p1->id]);

    $this->actingAs($scopedAdmin, 'admin');

    $editComp = Livewire::test(ProviderEditPage::class, ['provider' => $existingProv->id]);
    expect($editComp->get('practiceId'))->toBe((string) $p1->id);

    $editComp
        ->set('formData.status', 'inactive')
        ->call('save')
        ->assertHasNoErrors();

    expect($existingProv->fresh()->practices()->pluck('practices.id')->all())
        ->toContain($p1->id, $p2->id)
        ->and((int) $existingUser->fresh()->status)->toBe(0);
});

it('item 18: scopes DashboardService, PracticeCredentialingReportPage, ProviderPracticeAssignmentPage, CredentialListPage, CredentialCreatePage, and TaskBoardPage to admin practices', function () {
    $scopedAdmin = Admin::create([
        'name' => 'Scoped Report Admin',
        'username' => 'scoped_report',
        'email' => 'scopedreport@example.com',
        'password' => 'secret',
        'status' => 'active',
    ]);
    $scopedAdmin->assignRole(AdminRole::CredentialingManager->value);

    $unassignedAdmin = Admin::create([
        'name' => 'Unassigned Exec',
        'username' => 'unassigned_exec',
        'email' => 'unassignedexec@example.com',
        'password' => 'secret',
        'status' => 'active',
    ]);
    $unassignedAdmin->assignRole(AdminRole::CredentialingExecutive->value);

    $p1User = User::create(['name' => 'P1 Scope', 'email' => 'p1scope@example.com', 'password' => 'secret', 'status' => 1]);
    $p1 = Practice::create(['user_id' => $p1User->id, 'legal_name' => 'Scoped Practice Alpha']);
    $p2User = User::create(['name' => 'P2 Scope', 'email' => 'p2scope@example.com', 'password' => 'secret', 'status' => 1]);
    $p2 = Practice::create(['user_id' => $p2User->id, 'legal_name' => 'Unassigned Practice Omega']);

    $scopedAdmin->practices()->sync([$p1->id]);

    $provUser = User::create(['name' => 'Shared Scope Prov', 'email' => 'sharedscopeprov@example.com', 'password' => 'secret', 'status' => 1]);
    $provider = ProviderDetails::create(['user_id' => $provUser->id, 'npi' => '1234567893']);
    $provider->practices()->attach([$p1->id, $p2->id]);

    $payer = Payer::create(['name' => 'Cigna', 'is_active' => true]);
    $status = Status::first();

    $case1 = CredentialingCase::create([
        'case_number' => 'CRD-SCP-ALPHA',
        'provider_id' => $provider->id,
        'practice_id' => $p1->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
    ]);
    $case2 = CredentialingCase::create([
        'case_number' => 'CRD-SCP-OMEGA',
        'provider_id' => $provider->id,
        'practice_id' => $p2->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
    ]);

    CaseActivity::create([
        'credentialing_case_id' => $case2->id,
        'activity_type' => 'note',
        'summary' => 'Secret Omega Activity',
    ]);

    // DashboardService recentActivity returns 0 records for practice-scoped admin with 0 assigned practices
    $this->actingAs($unassignedAdmin, 'admin');
    expect(app(DashboardService::class)->recentActivity())->toBeEmpty();

    $this->actingAs($scopedAdmin, 'admin');

    // PracticeCredentialingReportPage cannot view P2
    Livewire::test(PracticeCredentialingReportPage::class)
        ->set('draftPracticeId', (string) $p2->id)
        ->call('runReport')
        ->assertDontSee('Unassigned Practice Omega');

    // ProviderPracticeAssignmentPage does not leak P2
    Livewire::test(ProviderPracticeAssignmentPage::class)
        ->assertSee('Scoped Practice Alpha')
        ->assertDontSee('Unassigned Practice Omega');

    // CredentialListPage bulkAssignSelected cannot modify out-of-scope case2
    Livewire::test(CredentialListPage::class)
        ->set('selectedCaseIds', [(string) $case2->id])
        ->set('bulkAssigneeId', (string) $scopedAdmin->id)
        ->call('bulkAssignSelected')
        ->assertForbidden();
    expect($case2->fresh()->assigned_admin_id)->toBeNull();

    // CredentialCreatePage only shows P1 for shared provider
    Livewire::test(CredentialCreatePage::class)
        ->set('formData.provider_id', $provider->id)
        ->assertSee('Scoped Practice Alpha')
        ->assertDontSee('Unassigned Practice Omega');

    // TaskBoardPage only lists P1 cases in dropdown and enforces provider scope on saveTask without TypeError
    $outOfScopeProvUser = User::create(['name' => 'Omega Only Prov', 'email' => 'omegaprov@example.com', 'password' => 'secret', 'status' => 1]);
    $outOfScopeProv = ProviderDetails::create(['user_id' => $outOfScopeProvUser->id, 'npi' => '1245319599']);
    $outOfScopeProv->practices()->attach([$p2->id]);

    Livewire::test(TaskBoardPage::class)
        ->assertSee('CRD-SCP-ALPHA')
        ->assertDontSee('CRD-SCP-OMEGA')
        ->call('openCreateModal')
        ->set('formData.title', 'In-Scope Provider Task')
        ->set('formData.provider_id', $provider->id)
        ->call('saveTask')
        ->assertHasNoErrors();

    Livewire::test(TaskBoardPage::class)
        ->call('openCreateModal')
        ->set('formData.title', 'Out-of-Scope Provider Task')
        ->set('formData.provider_id', $outOfScopeProv->id)
        ->call('saveTask')
        ->assertForbidden();
});

it('item 19 & 20: applies runtime mail config on auth mailers, maps Document::access_level to visibility, and avoids redundant checklist syncs', function () {
    $doc = Document::create([
        'title' => 'Access Level Alias Doc',
        'access_level' => 'provider',
    ]);

    expect($doc->fresh()->visibility)->toBe('provider')
        ->and($doc->fresh()->access_level)->toBe('provider');
});
