<?php

use App\Data\MailMessageDto;
use App\Enums\AdminRole;
use App\Livewire\Admin\Email\EmailViewPage;
use App\Mail\CredentialingTemplateMail;
use App\Models\Admin;
use App\Models\CredentialingCase;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\Status;
use App\Models\User;
use App\Services\CredentialingEmailService;
use App\Services\EmailCaseLinkService;
use App\Services\EmailMatchingService;
use App\Services\GraphMailboxService;
use App\Services\MailSettingsService;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(AdminPermissionSeeder::class);
    $this->seed(AdminSeeder::class);
    $this->seed(MasterDataSeeder::class);
});

test('CredentialingEmailService::send attaches single and multiple CC recipients to the Mailable', function () {
    Mail::fake();

    app(MailSettingsService::class)->saveSettings([
        'enabled' => true,
        'host' => 'smtp.office365.com',
        'port' => 587,
        'encryption' => 'tls',
        'scheme' => 'smtp',
        'username' => 'credentialing@revantagehbs.com',
        'password' => 'smtp-password',
        'from_address' => 'credentialing@revantagehbs.com',
        'from_name' => 'Revantage Credentialing',
        'imap_enabled' => false,
    ]);

    $service = app(CredentialingEmailService::class);

    $result = $service->send(
        case: null,
        toAddress: 'provider@example.com',
        subject: 'Follow-up on Credentialing',
        body: 'Please send updated license.',
        adminId: null,
        template: null,
        ccAddress: 'manager@example.com, billing@example.com; supervisor@example.com',
    );

    expect($result->success)->toBeTrue();

    Mail::assertSent(CredentialingTemplateMail::class, function (CredentialingTemplateMail $mail) {
        return $mail->hasTo('provider@example.com')
            && $mail->hasCc('manager@example.com')
            && $mail->hasCc('billing@example.com')
            && $mail->hasCc('supervisor@example.com');
    });
});

test('EmailMatchingService::match matches case numbers with 6 to 12 character alphanumeric prefixes', function () {
    $practiceUser = User::create([
        'name' => 'Revantage Practice',
        'email' => 'revantage1@example.com',
        'password' => 'password123',
    ]);

    $practice = Practice::create([
        'user_id' => $practiceUser->id,
        'legal_name' => 'Revantage Health Partners',
        'client_code' => 'REVANTAGE1',
        'email' => 'revantage1@example.com',
        'status' => 'active',
    ]);

    $providerUser = User::create([
        'name' => 'Dr. Alphanumeric Match',
        'email' => 'dr-match@example.com',
        'password' => 'password123',
    ]);

    $provider = ProviderDetails::create([
        'user_id' => $providerUser->id,
        'npi' => '1234567890',
        'status' => 'active',
    ]);

    $payer = Payer::create([
        'name' => 'Aetna',
        'slug' => 'aetna-match',
        'payer_type' => 'Commercial',
    ]);

    $status = Status::firstOrFail();

    $case = CredentialingCase::create([
        'case_number' => 'REVANTAGE1-2026-0001',
        'provider_id' => $provider->id,
        'practice_id' => $practice->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
        'state' => 'TX',
    ]);

    $matchingService = app(EmailMatchingService::class);
    $result = $matchingService->match(
        'Re: Status update for revantage1-2026-0001',
        'Please find the requested documents attached.',
        'external@payer.com'
    );

    expect($result['confidence'])->toBe('high')
        ->and($result['case'])->not->toBeNull()
        ->and($result['case']->id)->toBe($case->id)
        ->and($result['provider']?->id)->toBe($provider->id);
});

test('EmailAttachmentDownloadController forces attachment disposition for image/svg+xml even when inline=1 is requested', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $messageId = EmailViewPage::encodeId('msg-svg-1');
    $attachmentId = EmailViewPage::encodeId('att-svg-1');

    $this->mock(GraphMailboxService::class, function ($mock) {
        $mock->shouldReceive('downloadAttachment')
            ->once()
            ->andReturn([
                'name' => 'malicious.svg',
                'contentType' => 'image/svg+xml',
                'content' => '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>',
            ]);
    });

    $response = $this->actingAs($admin, 'admin')
        ->get(route('admin.email.attachment', [
            'folder' => 'inbox',
            'messageId' => $messageId,
            'attachmentId' => $attachmentId,
        ]).'?inline=1');

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Disposition', 'attachment; filename="malicious.svg"; filename*=UTF-8\'\'malicious.svg');
});

test('EmailAttachmentDownloadController enforces AdminScopeService when email is linked to a case', function () {
    $scopedAdmin = Admin::create([
        'name' => 'Scoped Executive',
        'username' => 'scoped_exec_email',
        'email' => 'scoped-exec-email@example.com',
        'password' => 'password123',
        'status' => 'active',
    ]);
    $scopedAdmin->assignRole(AdminRole::CredentialingExecutive->value);

    $practiceA = Practice::create([
        'user_id' => User::create(['name' => 'PA', 'email' => 'pa@example.com', 'password' => 'password'])->id,
        'legal_name' => 'Assigned Practice',
        'client_code' => 'ASGN01',
        'email' => 'pa@example.com',
        'status' => 'active',
    ]);

    $practiceB = Practice::create([
        'user_id' => User::create(['name' => 'PB', 'email' => 'pb@example.com', 'password' => 'password'])->id,
        'legal_name' => 'Unassigned Practice',
        'client_code' => 'UNAS01',
        'email' => 'pb@example.com',
        'status' => 'active',
    ]);

    $scopedAdmin->practices()->sync([$practiceA->id]);

    $provider = ProviderDetails::create([
        'user_id' => User::create(['name' => 'Prov', 'email' => 'prov@example.com', 'password' => 'password'])->id,
        'npi' => '9876543210',
        'status' => 'active',
    ]);

    $unassignedCase = CredentialingCase::create([
        'provider_id' => $provider->id,
        'practice_id' => $practiceB->id,
        'payer_id' => Payer::create(['name' => 'Cigna', 'is_active' => true])->id,
        'status_id' => Status::firstOrFail()->id,
        'state' => 'CA',
    ]);

    $rawMessageId = 'graph-msg-unassigned-case';
    app(EmailCaseLinkService::class)->link($rawMessageId, $unassignedCase->id);

    $this->actingAs($scopedAdmin, 'admin')
        ->get(route('admin.email.attachment', [
            'folder' => 'inbox',
            'messageId' => EmailViewPage::encodeId($rawMessageId),
            'attachmentId' => EmailViewPage::encodeId('att-1'),
        ]))
        ->assertForbidden();
});

test('MailMessageDto::sanitizeHtmlForDisplay strips relative /admin/ URLs from img and a tags', function () {
    $raw = '<p>Hello</p><img src="/admin/settings/users?delete=1" alt="csrf"><a href="/admin/logout">Click</a>';
    $sanitized = MailMessageDto::sanitizeHtmlForDisplay($raw);

    expect($sanitized)
        ->not->toContain('/admin/settings/users')
        ->not->toContain('/admin/logout');
});
