<?php

use App\Models\EmailMessage;
use App\Services\CredentialingEmailService;
use App\Services\GraphMailboxService;
use App\Services\MailSettingsService;
use App\Services\MicrosoftGraphTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function configureGraphForTests(): void
{
    config([
        'services.microsoft_graph.tenant_id' => '014947f0-8696-4ff4-b4b0-873d91eb1665',
        'services.microsoft_graph.client_id' => 'f48ae449-09c4-46c6-b187-d1238ee09190',
        'services.microsoft_graph.client_secret' => 'test-secret-value',
        'services.microsoft_graph.mailbox' => 'credentialing@revantagehbs.com',
    ]);
}

function fakeGraphToken(): void
{
    Http::fake([
        'login.microsoftonline.com/*' => Http::response([
            'access_token' => 'fake-graph-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ], 200),
    ]);
}

test('microsoft graph token service reports configured when env present', function () {
    configureGraphForTests();

    $service = app(MicrosoftGraphTokenService::class);

    expect($service->isConfigured())->toBeTrue()
        ->and($service->missingConfigKeys())->toBe([]);
});

test('microsoft graph token service caches access token', function () {
    configureGraphForTests();
    fakeGraphToken();

    $service = app(MicrosoftGraphTokenService::class);

    expect($service->getAccessToken())->toBe('fake-graph-token')
        ->and($service->getAccessToken())->toBe('fake-graph-token');

    Http::assertSentCount(1);
});

test('graph mailbox sync imports inbox and sent messages', function () {
    configureGraphForTests();

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
        'imap_enabled' => true,
        'imap_host' => 'outlook.office365.com',
        'imap_port' => 993,
        'imap_encryption' => 'ssl',
        'imap_folder' => 'INBOX',
        'imap_sent_enabled' => true,
        'imap_sent_folder' => 'Sent Items',
        'imap_username' => 'credentialing@revantagehbs.com',
    ]);

    Http::fake([
        'login.microsoftonline.com/*' => Http::response([
            'access_token' => 'fake-graph-token',
            'expires_in' => 3600,
        ], 200),
        'graph.microsoft.com/v1.0/users/credentialing@revantagehbs.com/mailFolders/inbox/messages*' => Http::response([
            'value' => [
                [
                    'id' => 'inbox-msg-1',
                    'internetMessageId' => '<inbound-1@example.com>',
                    'receivedDateTime' => '2026-07-18T10:00:00Z',
                    'sentDateTime' => '2026-07-18T10:00:00Z',
                    'hasAttachments' => false,
                ],
            ],
        ], 200),
        'graph.microsoft.com/v1.0/users/credentialing@revantagehbs.com/messages/inbox-msg-1*' => Http::response([
            'id' => 'inbox-msg-1',
            'internetMessageId' => '<inbound-1@example.com>',
            'subject' => 'Provider reply',
            'body' => ['contentType' => 'text', 'content' => 'Hello from provider'],
            'from' => ['emailAddress' => ['address' => 'provider@example.com']],
            'toRecipients' => [
                ['emailAddress' => ['address' => 'credentialing@revantagehbs.com']],
            ],
            'receivedDateTime' => '2026-07-18T10:00:00Z',
            'sentDateTime' => '2026-07-18T10:00:00Z',
            'hasAttachments' => false,
            'internetMessageHeaders' => [],
        ], 200),
        'graph.microsoft.com/v1.0/users/credentialing@revantagehbs.com/mailFolders/sentitems/messages*' => Http::response([
            'value' => [
                [
                    'id' => 'sent-msg-1',
                    'internetMessageId' => '<outbound-1@revantagehbs.com>',
                    'receivedDateTime' => '2026-07-18T09:00:00Z',
                    'sentDateTime' => '2026-07-18T09:00:00Z',
                    'hasAttachments' => false,
                ],
            ],
        ], 200),
        'graph.microsoft.com/v1.0/users/credentialing@revantagehbs.com/messages/sent-msg-1*' => Http::response([
            'id' => 'sent-msg-1',
            'internetMessageId' => '<outbound-1@revantagehbs.com>',
            'subject' => 'Credentialing request',
            'body' => ['contentType' => 'html', 'content' => '<p>Please respond</p>'],
            'from' => ['emailAddress' => ['address' => 'credentialing@revantagehbs.com']],
            'toRecipients' => [
                ['emailAddress' => ['address' => 'provider@example.com']],
            ],
            'receivedDateTime' => '2026-07-18T09:00:00Z',
            'sentDateTime' => '2026-07-18T09:00:00Z',
            'hasAttachments' => false,
            'internetMessageHeaders' => [],
        ], 200),
    ]);

    $result = app(GraphMailboxService::class)->sync();

    expect($result['imported'])->toBe(2)
        ->and($result['skipped'])->toBe(0)
        ->and(EmailMessage::where('direction', 'inbound')->where('subject', 'Provider reply')->exists())->toBeTrue()
        ->and(EmailMessage::where('direction', 'outbound')->where('subject', 'Credentialing request')->exists())->toBeTrue();
});

test('graph mailbox sync skips duplicate message ids', function () {
    configureGraphForTests();

    EmailMessage::create([
        'direction' => 'inbound',
        'from_address' => 'provider@example.com',
        'to_address' => 'credentialing@revantagehbs.com',
        'subject' => 'Already imported',
        'body' => 'Body',
        'status' => 'received',
        'queue_category' => 'inbox',
        'message_id' => 'dup-1@example.com',
        'received_at' => now(),
    ]);

    Http::fake([
        'login.microsoftonline.com/*' => Http::response([
            'access_token' => 'fake-graph-token',
            'expires_in' => 3600,
        ], 200),
        'graph.microsoft.com/*/mailFolders/inbox/messages*' => Http::response([
            'value' => [
                [
                    'id' => 'inbox-msg-dup',
                    'internetMessageId' => '<dup-1@example.com>',
                    'receivedDateTime' => '2026-07-18T10:00:00Z',
                    'sentDateTime' => '2026-07-18T10:00:00Z',
                    'hasAttachments' => false,
                ],
            ],
        ], 200),
        'graph.microsoft.com/*/mailFolders/sentitems/messages*' => Http::response([
            'value' => [],
        ], 200),
    ]);

    app(MailSettingsService::class)->saveSettings([
        'enabled' => true,
        'host' => 'smtp.office365.com',
        'port' => 587,
        'encryption' => 'tls',
        'scheme' => 'smtp',
        'username' => 'credentialing@revantagehbs.com',
        'password' => 'smtp-password',
        'from_address' => 'credentialing@revantagehbs.com',
        'from_name' => 'Revantage',
        'imap_enabled' => true,
        'imap_host' => 'outlook.office365.com',
        'imap_port' => 993,
        'imap_encryption' => 'ssl',
        'imap_folder' => 'INBOX',
        'imap_sent_enabled' => true,
        'imap_sent_folder' => 'Sent Items',
        'imap_username' => 'credentialing@revantagehbs.com',
    ]);

    $result = app(GraphMailboxService::class)->sync();

    expect($result['imported'])->toBe(0)
        ->and($result['skipped'])->toBe(1)
        ->and(EmailMessage::count())->toBe(1);
});

test('syncInbox prefers graph when configured', function () {
    configureGraphForTests();

    Http::fake([
        'login.microsoftonline.com/*' => Http::response([
            'access_token' => 'fake-graph-token',
            'expires_in' => 3600,
        ], 200),
        'graph.microsoft.com/*/mailFolders/inbox/messages*' => Http::response(['value' => []], 200),
        'graph.microsoft.com/*/mailFolders/sentitems/messages*' => Http::response(['value' => []], 200),
    ]);

    app(MailSettingsService::class)->saveSettings([
        'enabled' => true,
        'host' => 'smtp.office365.com',
        'port' => 587,
        'encryption' => 'tls',
        'scheme' => 'smtp',
        'username' => 'credentialing@revantagehbs.com',
        'password' => 'smtp-password',
        'from_address' => 'credentialing@revantagehbs.com',
        'from_name' => 'Revantage',
        'imap_enabled' => true,
        'imap_host' => 'outlook.office365.com',
        'imap_port' => 993,
        'imap_encryption' => 'ssl',
        'imap_folder' => 'INBOX',
        'imap_sent_enabled' => true,
        'imap_sent_folder' => 'Sent Items',
        'imap_username' => 'credentialing@revantagehbs.com',
    ]);

    $mailSettings = app(MailSettingsService::class);

    expect($mailSettings->mailboxSyncDriver())->toBe('graph')
        ->and($mailSettings->isMailboxSyncConfigured())->toBeTrue();

    $result = app(CredentialingEmailService::class)->syncInbox();

    expect($result)->toHaveKeys(['imported', 'skipped', 'errors', 'has_more']);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.microsoft.com'));
});

test('graph mailbox sync reports has_more when batch limit reached', function () {
    configureGraphForTests();

    app(MailSettingsService::class)->saveSettings([
        'enabled' => true,
        'host' => 'smtp.office365.com',
        'port' => 587,
        'encryption' => 'tls',
        'scheme' => 'smtp',
        'username' => 'credentialing@revantagehbs.com',
        'password' => 'smtp-password',
        'from_address' => 'credentialing@revantagehbs.com',
        'from_name' => 'Revantage',
        'imap_enabled' => true,
        'imap_host' => 'outlook.office365.com',
        'imap_port' => 993,
        'imap_encryption' => 'ssl',
        'imap_folder' => 'INBOX',
        'imap_sent_enabled' => false,
        'imap_sent_folder' => 'Sent Items',
        'imap_username' => 'credentialing@revantagehbs.com',
    ]);

    $messages = collect(range(1, 3))->map(fn (int $i) => [
        'id' => "inbox-msg-{$i}",
        'internetMessageId' => "<batch-{$i}@example.com>",
        'receivedDateTime' => "2026-07-18T10:0{$i}:00Z",
        'sentDateTime' => "2026-07-18T10:0{$i}:00Z",
        'hasAttachments' => false,
    ])->all();

    Http::fake([
        'login.microsoftonline.com/*' => Http::response([
            'access_token' => 'fake-graph-token',
            'expires_in' => 3600,
        ], 200),
        'graph.microsoft.com/*/mailFolders/inbox/messages*' => Http::response([
            'value' => $messages,
            '@odata.nextLink' => 'https://graph.microsoft.com/v1.0/next-page',
        ], 200),
        'graph.microsoft.com/v1.0/next-page' => Http::response(['value' => []], 200),
        'graph.microsoft.com/*/messages/inbox-msg-*' => Http::response([
            'id' => 'inbox-msg-1',
            'internetMessageId' => '<batch-1@example.com>',
            'subject' => 'Batch message',
            'body' => ['contentType' => 'text', 'content' => 'Body'],
            'from' => ['emailAddress' => ['address' => 'provider@example.com']],
            'toRecipients' => [
                ['emailAddress' => ['address' => 'credentialing@revantagehbs.com']],
            ],
            'receivedDateTime' => '2026-07-18T10:01:00Z',
            'sentDateTime' => '2026-07-18T10:01:00Z',
            'hasAttachments' => false,
            'internetMessageHeaders' => [],
        ], 200),
    ]);

    $result = app(GraphMailboxService::class)->sync(1);

    expect($result['has_more'])->toBeTrue()
        ->and($result['imported'] + $result['skipped'])->toBe(1);
});

test('graph test connection returns folder counts', function () {
    configureGraphForTests();

    Http::fake([
        'login.microsoftonline.com/*' => Http::response([
            'access_token' => 'fake-graph-token',
            'expires_in' => 3600,
        ], 200),
        'graph.microsoft.com/*/mailFolders/inbox*' => Http::response([
            'displayName' => 'Inbox',
            'totalItemCount' => 12,
        ], 200),
        'graph.microsoft.com/*/mailFolders/sentitems*' => Http::response([
            'displayName' => 'Sent Items',
            'totalItemCount' => 5,
        ], 200),
    ]);

    $result = app(GraphMailboxService::class)->testConnection();

    expect($result['success'])->toBeTrue()
        ->and($result['mailbox'])->toBe('credentialing@revantagehbs.com')
        ->and($result['folders'])->toHaveCount(2);
});

test('mailbox sync falls back to imap when graph is not configured', function () {
    config([
        'services.microsoft_graph.tenant_id' => null,
        'services.microsoft_graph.client_id' => null,
        'services.microsoft_graph.client_secret' => null,
        'services.microsoft_graph.mailbox' => null,
    ]);

    $mailSettings = app(MailSettingsService::class);

    expect(app(GraphMailboxService::class)->isConfigured())->toBeFalse()
        ->and($mailSettings->mailboxSyncDriver())->toBe('imap');
});
