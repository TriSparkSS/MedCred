<?php

use App\Data\MailMessageDto;
use App\Models\EmailCaseLink;
use App\Models\EmailMessage;
use App\Services\CredentialingEmailService;
use App\Services\GraphMailboxService;
use App\Services\MailSettingsService;
use App\Services\MicrosoftGraphTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function configureGraphForTests(): void
{
    config([
        'services.microsoft_graph.tenant_id' => '014947f0-8696-4ff4-b4b0-873d91eb1665',
        'services.microsoft_graph.client_id' => 'f48ae449-09c4-46c6-b187-d1238ee09190',
        'services.microsoft_graph.client_secret' => 'test-secret-value',
        'services.microsoft_graph.mailbox' => 'credentialing@revantagehbs.com',
    ]);

    Cache::flush();
}

beforeEach(function () {
    Http::preventStrayRequests();
});

test('microsoft graph token service reports configured when env present', function () {
    configureGraphForTests();

    $service = app(MicrosoftGraphTokenService::class);

    expect($service->isConfigured())->toBeTrue()
        ->and($service->missingConfigKeys())->toBe([]);
});

test('graph listFolder returns live messages without persisting email_messages', function () {
    configureGraphForTests();

    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $url = $request->url();

        if (str_contains($url, 'login.microsoftonline.com')) {
            return Http::response([
                'access_token' => 'fake-graph-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200);
        }

        if (str_contains($url, '/mailFolders/inbox') && ! str_contains($url, '/messages')) {
            return Http::response(['totalItemCount' => 1], 200);
        }

        if (str_contains($url, '/mailFolders/inbox/messages')) {
            return Http::response([
                '@odata.count' => 1,
                'value' => [[
                    'id' => 'msg-1',
                    'subject' => 'Hello from Graph',
                    'from' => ['emailAddress' => ['address' => 'provider@example.com', 'name' => 'Provider']],
                    'toRecipients' => [['emailAddress' => ['address' => 'credentialing@revantagehbs.com']]],
                    'receivedDateTime' => now()->toIso8601String(),
                    'hasAttachments' => false,
                    'conversationId' => 'conv-1',
                    'internetMessageId' => '<hello@example.com>',
                    'isRead' => true,
                ]],
            ], 200);
        }

        return Http::response(['error' => ['message' => 'Unexpected URL: '.$url]], 500);
    });

    $page = app(GraphMailboxService::class)->listFolder('inbox', 1, 15);

    expect($page->items)->toHaveCount(1)
        ->and($page->items->first())->toBeInstanceOf(MailMessageDto::class)
        ->and($page->items->first()->subject)->toBe('Hello from Graph')
        ->and(EmailMessage::count())->toBe(0);
});

test('graph getMessage returns standalone conversation when no peers found', function () {
    configureGraphForTests();

    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $url = $request->url();

        if (str_contains($url, 'login.microsoftonline.com')) {
            return Http::response([
                'access_token' => 'fake-graph-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200);
        }

        if (str_contains($url, '/messages/msg-solo')) {
            return Http::response([
                'id' => 'msg-solo',
                'subject' => 'Standalone',
                'body' => ['contentType' => 'text', 'content' => 'Only me'],
                'from' => ['emailAddress' => ['address' => 'provider@example.com', 'name' => 'Provider']],
                'toRecipients' => [['emailAddress' => ['address' => 'credentialing@revantagehbs.com']]],
                'receivedDateTime' => now()->toIso8601String(),
                'hasAttachments' => false,
                'conversationId' => null,
                'internetMessageId' => '<solo@example.com>',
                'internetMessageHeaders' => [],
                'isRead' => true,
            ], 200);
        }

        if (str_contains($url, 'internetMessageId')) {
            return Http::response(['value' => []], 200);
        }

        return Http::response(['value' => []], 200);
    });

    $thread = app(GraphMailboxService::class)->getConversation(null, 'msg-solo', 'inbox');

    expect($thread)->toHaveCount(1)
        ->and($thread->first()->subject)->toBe('Standalone')
        ->and(EmailMessage::count())->toBe(0);
});

test('email case link normalizes message ids', function () {
    expect(EmailCaseLink::normalizeMessageId('<Abc@Example.com>'))->toBe('Abc@Example.com')
        ->and(EmailCaseLink::normalizeMessageId('plain@example.com'))->toBe('plain@example.com')
        ->and(EmailCaseLink::normalizeMessageId(null))->toBeNull();
});

test('send does not create email_messages rows', function () {
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
        'imap_host' => 'outlook.office365.com',
        'imap_port' => 993,
        'imap_encryption' => 'ssl',
        'imap_folder' => 'INBOX',
        'imap_sent_enabled' => false,
        'imap_sent_folder' => 'Sent Items',
        'imap_username' => 'credentialing@revantagehbs.com',
    ]);

    $result = app(CredentialingEmailService::class)->send(
        null,
        'provider@example.com',
        'Subject line',
        'Body text',
    );

    expect($result->success)->toBeTrue()
        ->and(EmailMessage::count())->toBe(0)
        ->and(EmailCaseLink::count())->toBe(0);

    Mail::assertSent(\App\Mail\CredentialingTemplateMail::class);
});

test('mailbox sync driver is always graph', function () {
    configureGraphForTests();

    expect(app(MailSettingsService::class)->mailboxSyncDriver())->toBe('graph')
        ->and(app(MailSettingsService::class)->isMailboxSyncConfigured())->toBeTrue();
});
