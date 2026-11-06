<?php

use App\Livewire\Admin\Email\EmailDashboardPage;
use App\Livewire\Admin\Master\SlaRuleManager;
use App\Mail\CredentialingTemplateMail;
use App\Models\Admin;
use App\Models\CaseSlaEvent;
use App\Models\CaseSlaTimer;
use App\Models\CaseType;
use App\Models\CredentialingCase;
use App\Models\DelayOwner;
use App\Models\EmailMessage;
use App\Models\NotificationTemplate;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\SlaRule;
use App\Models\Status;
use App\Models\User;
use App\Services\CredentialingEmailService;
use App\Services\DelayOwnershipService;
use App\Services\EmailMatchingService;
use App\Services\MailSettingsService;
use App\Services\SlaEngineService;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(AdminPermissionSeeder::class);
    $this->seed(AdminSeeder::class);
    $this->seed(MasterDataSeeder::class);
    Http::preventStrayRequests();
});

function createWave4Fixture(string $tag): array
{
    $practiceUser = User::create([
        'name' => "Practice User {$tag}",
        'email' => "practice-{$tag}@example.com",
        'password' => 'password123',
    ]);

    $practice = Practice::create([
        'user_id' => $practiceUser->id,
        'legal_name' => "Sunrise Clinic {$tag}",
        'client_code' => "W4{$tag}",
        'ein_tin' => '74-1234567',
        'email' => "practice-{$tag}@example.com",
        'status' => 'active',
    ]);

    $providerUser = User::create([
        'name' => "Dr. Elena Vance {$tag}",
        'email' => "elena-{$tag}@example.com",
        'password' => 'password123',
    ]);

    $provider = ProviderDetails::create([
        'user_id' => $providerUser->id,
        'npi' => '1456789012',
        'caqh_id' => '13987654',
        'status' => 'active',
    ]);

    $provider->practices()->attach($practice->id, ['primary_flag' => true]);

    $payer = Payer::create([
        'name' => "CignaHealth {$tag}",
        'email' => "credentialing@cigna-{$tag}.com",
        'is_active' => true,
    ]);

    $status = Status::where('dashboard_category', 'provider')->first()
        ?? Status::firstOrFail();

    $case = CredentialingCase::create([
        'provider_id' => $provider->id,
        'practice_id' => $practice->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
        'state' => 'TX',
    ]);

    return [$practice, $provider, $payer, $status, $case];
}

test('BRD #21: EmailMatchingService matches by CAQH ID, Practice Tax ID, Provider + Payer/Domain, and detects bounces', function () {
    [$practice, $provider, $payer, , $case] = createWave4Fixture('M21');

    $matching = app(EmailMatchingService::class);

    // 1. Match by CAQH ID
    $caqhMatch = $matching->match(
        'Application Update for CAQH 13987654',
        'Please review the attestation for CAQH ID 13987654.',
        'updates@random.org'
    );
    expect($caqhMatch['confidence'])->toBe('medium')
        ->and($caqhMatch['case']?->id)->toBe($case->id)
        ->and($caqhMatch['provider']?->id)->toBe($provider->id);

    // 2. Match by Practice Tax ID + Payer Name
    $taxMatch = $matching->match(
        "Enrollment Roster for Tax ID 741234567 - {$payer->name}",
        'Group TIN 74-1234567 is pending verification.',
        'enrollments@carrier.org'
    );
    expect($taxMatch['confidence'])->toBe('medium')
        ->and($taxMatch['case']?->id)->toBe($case->id)
        ->and($taxMatch['practice']?->id)->toBe($practice->id)
        ->and($taxMatch['payer']?->id)->toBe($payer->id);

    // 3. Match by Provider Name + Payer Email Domain
    $domainMatch = $matching->match(
        'Provider file review for Elena Vance M21',
        'We need an updated W-9 for Elena Vance M21.',
        'rep@cigna-M21.com'
    );
    expect($domainMatch['confidence'])->toBe('medium')
        ->and($domainMatch['case']?->id)->toBe($case->id)
        ->and($domainMatch['provider']?->id)->toBe($provider->id)
        ->and($domainMatch['payer']?->id)->toBe($payer->id);

    // 4. Bounce / NDR detection
    expect($matching->isBounceOrFailure('Undeliverable: Credentialing Packet', 'postmaster@outlook.com', '550 5.1.1 User unknown'))->toBeTrue()
        ->and($matching->isBounceOrFailure('Delivery Status Notification (Failure)', 'MAILER-DAEMON@mail.example.com', 'Mail delivery failed'))->toBeTrue()
        ->and($matching->isBounceOrFailure('Re: Credentialing Packet Approved', 'provider@example.com', 'Thank you!'))->toBeFalse();
});

test('BRD #22: CredentialingEmailService saves outbound EmailMessage rows on send and persists linked emails on the case', function () {
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

    [$practice, $provider, $payer, , $case] = createWave4Fixture('E22');
    $admin = Admin::where('username', 'superadmin')->firstOrFail();

    $emailService = app(CredentialingEmailService::class);

    // 1. Outbound send persists EmailMessage on the case
    $result = $emailService->send(
        case: $case,
        toAddress: 'elena-E22@example.com',
        subject: 'Missing DEA Certificate',
        body: '<p>Please upload your DEA certificate.</p>',
        adminId: $admin->id,
        ccAddress: 'manager@example.com',
    );

    expect($result->success)->toBeTrue();
    Mail::assertSent(CredentialingTemplateMail::class);

    $savedOutbound = EmailMessage::where('message_id', $result->messageId)->first();
    expect($savedOutbound)->not->toBeNull()
        ->and($savedOutbound->credentialing_case_id)->toBe($case->id)
        ->and($savedOutbound->provider_id)->toBe($provider->id)
        ->and($savedOutbound->direction)->toBe('outbound')
        ->and($savedOutbound->status)->toBe('sent')
        ->and($savedOutbound->is_unlinked)->toBeFalse()
        ->and($savedOutbound->sent_by_admin_id)->toBe($admin->id);

    // 2. linkMessageToCase fetches from Graph when messageMeta is empty and saves EmailMessage on the case
    config([
        'services.microsoft_graph.tenant_id' => '014947f0-8696-4ff4-b4b0-873d91eb1665',
        'services.microsoft_graph.client_id' => 'f48ae449-09c4-46c6-b187-d1238ee09190',
        'services.microsoft_graph.client_secret' => 'test-secret-value',
        'services.microsoft_graph.mailbox' => 'credentialing@revantagehbs.com',
    ]);
    Cache::flush();

    Http::fake(function (Request $request) {
        $url = $request->url();
        if (str_contains($url, 'login.microsoftonline.com')) {
            return Http::response(['access_token' => 'fake-token', 'expires_in' => 3600], 200);
        }
        if (str_contains($url, '/messages/')) {
            return Http::response([
                'id' => 'graph-msg-22',
                'internetMessageId' => '<inbound-22@cigna.com>',
                'conversationId' => 'conv-22',
                'subject' => 'Payer Acknowledgement for Dr. Elena Vance',
                'body' => ['contentType' => 'html', 'content' => '<p>Application received by payer.</p>'],
                'from' => ['emailAddress' => ['address' => 'enrollments@cigna.com', 'name' => 'Cigna']],
                'toRecipients' => [['emailAddress' => ['address' => 'credentialing@revantagehbs.com']]],
                'receivedDateTime' => now()->toIso8601String(),
                'hasAttachments' => false,
                'isRead' => false,
            ], 200);
        }

        return Http::response(['value' => []], 200);
    });

    $emailService->linkMessageToCase('<inbound-22@cigna.com>', $case, $admin->id);

    $linkedMsg = EmailMessage::where('credentialing_case_id', $case->id)
        ->where('direction', 'inbound')
        ->first();

    expect($linkedMsg)->not->toBeNull()
        ->and($linkedMsg->provider_id)->toBe($provider->id)
        ->and($linkedMsg->from_address)->toBe('enrollments@cigna.com')
        ->and($linkedMsg->subject)->toBe('Payer Acknowledgement for Dr. Elena Vance')
        ->and($linkedMsg->is_unlinked)->toBeFalse();
});

test('BRD #23: EmailDashboardPage filters unread, unlinked, and bounced queues and displays smart-tag case suggestions', function () {
    [, $provider, , , $case] = createWave4Fixture('Q23');
    $admin = Admin::where('username', 'superadmin')->firstOrFail();

    config([
        'services.microsoft_graph.tenant_id' => '014947f0-8696-4ff4-b4b0-873d91eb1665',
        'services.microsoft_graph.client_id' => 'f48ae449-09c4-46c6-b187-d1238ee09190',
        'services.microsoft_graph.client_secret' => 'test-secret-value',
        'services.microsoft_graph.mailbox' => 'credentialing@revantagehbs.com',
    ]);
    Cache::flush();

    Http::fake(function (Request $request) {
        $url = $request->url();
        if (str_contains($url, 'login.microsoftonline.com')) {
            return Http::response(['access_token' => 'fake-token', 'expires_in' => 3600], 200);
        }
        if (str_contains($url, '/mailFolders/inbox') && ! str_contains($url, '/messages')) {
            return Http::response(['totalItemCount' => 3], 200);
        }
        if (str_contains($url, '/mailFolders/sentitems') && ! str_contains($url, '/messages')) {
            return Http::response(['totalItemCount' => 1], 200);
        }
        if (str_contains($url, '/mailFolders/inbox/messages')) {
            return Http::response([
                '@odata.count' => 3,
                'value' => [
                    [
                        'id' => 'msg-unread-caqh',
                        'subject' => 'Unread CAQH 13987654 Update',
                        'from' => ['emailAddress' => ['address' => 'caqh@example.com', 'name' => 'CAQH']],
                        'toRecipients' => [['emailAddress' => ['address' => 'credentialing@revantagehbs.com']]],
                        'receivedDateTime' => now()->subMinutes(5)->toIso8601String(),
                        'hasAttachments' => false,
                        'conversationId' => 'c1',
                        'internetMessageId' => '<unread-caqh@example.com>',
                        'isRead' => false,
                    ],
                    [
                        'id' => 'msg-read-normal',
                        'subject' => 'General Inquiry',
                        'from' => ['emailAddress' => ['address' => 'info@example.com', 'name' => 'Info']],
                        'toRecipients' => [['emailAddress' => ['address' => 'credentialing@revantagehbs.com']]],
                        'receivedDateTime' => now()->subHour()->toIso8601String(),
                        'hasAttachments' => false,
                        'conversationId' => 'c2',
                        'internetMessageId' => '<read-normal@example.com>',
                        'isRead' => true,
                    ],
                    [
                        'id' => 'msg-bounce-ndr',
                        'subject' => 'Undeliverable: Application Packet',
                        'from' => ['emailAddress' => ['address' => 'mailer-daemon@outlook.com', 'name' => 'Mail Delivery Subsystem']],
                        'toRecipients' => [['emailAddress' => ['address' => 'credentialing@revantagehbs.com']]],
                        'receivedDateTime' => now()->subMinutes(20)->toIso8601String(),
                        'hasAttachments' => false,
                        'conversationId' => 'c3',
                        'internetMessageId' => '<bounce-ndr@example.com>',
                        'isRead' => true,
                    ],
                ],
            ], 200);
        }

        return Http::response(['value' => []], 200);
    });

    $component = Livewire::actingAs($admin, 'admin')
        ->test(EmailDashboardPage::class)
        ->assertSee('Unread Priority')
        ->assertSee('Unlinked')
        ->assertSee('Bounced / Failed')
        ->assertSee('Suggested: '.$case->case_number);

    // Filter: Unread Priority
    $component->call('setFilter', 'unread')
        ->assertSet('filter', 'unread')
        ->assertSee('Unread CAQH 13987654 Update')
        ->assertDontSee('General Inquiry');

    // Filter: Bounced / Failed
    $component->call('setFilter', 'bounced')
        ->assertSet('filter', 'bounced')
        ->assertSee('Undeliverable: Application Packet')
        ->assertDontSee('General Inquiry');

    // Quick-link the unlinked CAQH message to the suggested case
    $component->call('setFilter', 'unlinked')
        ->call('quickLinkCase', '<unread-caqh@example.com>', $case->id);

    expect(EmailMessage::where('credentialing_case_id', $case->id)->exists())->toBeTrue();
});

test('BRD #24: SlaRuleManager and SlaEngineService support granular Payer, Practice, and Case Type rules and inactive overrides', function () {
    [$practice, $provider, $payer, $status, $case] = createWave4Fixture('S24');
    $admin = Admin::where('username', 'superadmin')->firstOrFail();

    $caseType = CaseType::create(['name' => 'Initial Enrollment', 'is_active' => true]);
    $case->update(['case_type_id' => $caseType->id]);

    // Create global fallback rule via SlaRuleManager
    Livewire::actingAs($admin, 'admin')
        ->test(SlaRuleManager::class)
        ->call('openCreateModal')
        ->set('formData.name', 'Global Provider Reminder 1')
        ->set('formData.rule_key', 'provider_reminder_1')
        ->set('formData.dashboard_category', 'provider')
        ->set('formData.business_days', 5)
        ->set('formData.action', 'reminder')
        ->set('formData.is_active', true)
        ->call('save')
        ->assertHasNoErrors();

    // Create payer + practice + case_type specific rule with the SAME rule_key via SlaRuleManager
    Livewire::actingAs($admin, 'admin')
        ->test(SlaRuleManager::class)
        ->call('openCreateModal')
        ->set('formData.name', 'Cigna Sunrise Initial Reminder 1')
        ->set('formData.rule_key', 'provider_reminder_1')
        ->set('formData.payer_id', $payer->id)
        ->set('formData.practice_id', $practice->id)
        ->set('formData.case_type_id', $caseType->id)
        ->set('formData.dashboard_category', 'provider')
        ->set('formData.business_days', 2)
        ->set('formData.action', 'reminder')
        ->set('formData.is_active', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(SlaRule::where('rule_key', 'provider_reminder_1')->count())->toBe(2);

    $slaEngine = app(SlaEngineService::class);
    $resolved = $slaEngine->resolveRuleForCase($case, 'provider_reminder_1');

    expect($resolved)->not->toBeNull()
        ->and($resolved->name)->toBe('Cigna Sunrise Initial Reminder 1')
        ->and($resolved->business_days)->toBe(2);

    // Turning off the specific rule disables reminders for that combination instead of falling back to global
    $resolved->update(['is_active' => false]);
    expect($slaEngine->resolveRuleForCase($case, 'provider_reminder_1'))->toBeNull();
});

test('BRD #25: SlaEngineService respects automation_paused, manual delay overrides, and stops duplicate reminder emails', function () {
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

    [$practice, $provider, $payer, $status, $case] = createWave4Fixture('S25');
    $admin = Admin::where('username', 'superadmin')->firstOrFail();

    $template = NotificationTemplate::create([
        'name' => 'Provider Reminder Template',
        'template_key' => 'provider_reminder_w4',
        'subject' => 'Reminder for {{case_number}}',
        'body' => 'Dear {{provider_name}}, please submit documents.',
        'category' => 'sla',
        'is_active' => true,
    ]);

    SlaRule::create([
        'name' => 'Provider Reminder 1',
        'rule_key' => 'provider_reminder_1',
        'dashboard_category' => 'provider',
        'business_days' => 3,
        'action' => 'reminder',
        'notification_template_id' => $template->id,
        'is_active' => true,
    ]);

    $slaEngine = app(SlaEngineService::class);

    // 1. Paused automation skips due timer and marks it completed without sending email
    $case->update(['automation_paused' => true, 'automation_pause_reason' => 'Provider on leave']);
    $pausedTimer = CaseSlaTimer::create([
        'credentialing_case_id' => $case->id,
        'rule_key' => 'provider_reminder_1',
        'started_at' => now()->subDays(5),
        'due_at' => now()->subHour(),
        'status' => 'active',
    ]);

    $resPaused = $slaEngine->runDueChecks();
    expect($resPaused['reminders'])->toBe(0)
        ->and($pausedTimer->fresh()->status)->toBe('completed');
    Mail::assertNothingSent();

    // 2. Manual delay override to a different delay owner category skips provider reminder
    $case->update(['automation_paused' => false, 'automation_pause_reason' => null]);
    $internalOwner = DelayOwner::where('name', 'MedCred Team')->firstOrFail();
    app(DelayOwnershipService::class)->overrideDelayOwner($case, $internalOwner->id, 'Internal audit hold', $admin->id);

    $overrideTimer = CaseSlaTimer::create([
        'credentialing_case_id' => $case->id,
        'rule_key' => 'provider_reminder_1',
        'started_at' => now()->subDays(5),
        'due_at' => now()->subHour(),
        'status' => 'active',
    ]);

    $resOverride = $slaEngine->runDueChecks();
    expect($resOverride['reminders'])->toBe(0)
        ->and($overrideTimer->fresh()->status)->toBe('completed');
    Mail::assertNothingSent();

    // 3. Clear override (or set delay owner to Provider/Practice) -> sends 1st reminder, and blocks duplicate within 24h
    $providerOwner = DelayOwner::where('name', 'like', '%Provider%')->firstOrFail();
    $case->update(['delay_owner_id' => $providerOwner->id]);

    $validTimer1 = CaseSlaTimer::create([
        'credentialing_case_id' => $case->id,
        'rule_key' => 'provider_reminder_1',
        'started_at' => now()->subDays(4),
        'due_at' => now()->subHour(),
        'status' => 'active',
    ]);

    $resFirst = $slaEngine->runDueChecks();
    expect($resFirst['reminders'])->toBe(1)
        ->and($validTimer1->fresh()->status)->toBe('triggered');
    Mail::assertSent(CredentialingTemplateMail::class, 1);

    // Attempt a duplicate timer for the same rule_key within 24 hours
    $duplicateTimer = CaseSlaTimer::create([
        'credentialing_case_id' => $case->id,
        'rule_key' => 'provider_reminder_1',
        'started_at' => now()->subHours(2),
        'due_at' => now()->subMinute(),
        'status' => 'active',
    ]);

    $resDuplicate = $slaEngine->runDueChecks();
    expect($resDuplicate['reminders'])->toBe(0)
        ->and($duplicateTimer->fresh()->status)->toBe('completed')
        ->and(CaseSlaEvent::where('credentialing_case_id', $case->id)->where('rule_key', 'provider_reminder_1')->where('event_type', 'reminder')->count())->toBe(1);

    Mail::assertSent(CredentialingTemplateMail::class, 1);
});
