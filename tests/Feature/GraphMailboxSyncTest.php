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

test('graph getMessage loads attachment metadata without invalid odata select', function () {
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

        if (str_contains($url, '/messages/msg-att/attachments')) {
            $select = $request->data()['$select'] ?? null;
            if (is_string($select) && str_contains($select, '@odata.type')) {
                return Http::response([
                    'error' => ['message' => "Parsing OData Select and Expand failed: Term '@odata.type' is not valid in a \$select or \$expand expression."],
                ], 400);
            }

            return Http::response([
                'value' => [[
                    '@odata.type' => '#microsoft.graph.fileAttachment',
                    'id' => 'att-1',
                    'name' => 'report.pdf',
                    'size' => 1024,
                    'contentType' => 'application/pdf',
                ]],
            ], 200);
        }

        if (str_contains($url, '/messages/msg-att')) {
            return Http::response([
                'id' => 'msg-att',
                'subject' => 'With attachment',
                'body' => ['contentType' => 'text', 'content' => 'See attached'],
                'from' => ['emailAddress' => ['address' => 'provider@example.com', 'name' => 'Provider']],
                'toRecipients' => [['emailAddress' => ['address' => 'credentialing@revantagehbs.com']]],
                'receivedDateTime' => now()->toIso8601String(),
                'hasAttachments' => true,
                'conversationId' => 'conv-att',
                'internetMessageId' => '<att@example.com>',
                'internetMessageHeaders' => [],
                'isRead' => true,
            ], 200);
        }

        if (str_contains($url, 'conversationId')) {
            return Http::response(['value' => []], 200);
        }

        return Http::response(['value' => []], 200);
    });

    $message = app(GraphMailboxService::class)->getMessage('msg-att', 'inbox', true);

    expect($message->hasAttachments)->toBeTrue()
        ->and($message->attachments)->toHaveCount(1)
        ->and($message->attachments[0]['name'])->toBe('report.pdf')
        ->and($message->attachments[0]['contentType'])->toBe('application/pdf');
});

test('mail message dto rewrites inline cid images to attachment urls', function () {
    $dto = MailMessageDto::fromGraph([
        'id' => 'msg-inline',
        'subject' => 'Inline image',
        'body' => ['contentType' => 'html', 'content' => '<p>Hello</p><img src="cid:logo@abc" alt="Logo">'],
        'from' => ['emailAddress' => ['address' => 'sender@example.com', 'name' => 'Sender']],
        'toRecipients' => [['emailAddress' => ['address' => 'credentialing@revantagehbs.com']]],
        'receivedDateTime' => now()->toIso8601String(),
        'hasAttachments' => true,
        'attachments' => [[
            '@odata.type' => '#microsoft.graph.fileAttachment',
            'id' => 'att-inline',
            'name' => 'logo.png',
            'contentType' => 'image/png',
            'contentId' => 'logo@abc',
            'isInline' => true,
        ]],
    ], 'inbox', 'credentialing@revantagehbs.com');

    $html = $dto->bodyHtmlWithInlineAttachments('inbox', MailMessageDto::encodeGraphId('msg-inline'));

    expect($html)->toContain('/admin/emails/attachment/inbox/')
        ->and($html)->toContain('<img')
        ->and($html)->toContain('src=')
        ->and($html)->not->toContain('cid:logo@abc');
});

test('email html sanitizer preserves img src and strips scripts', function () {
    $raw = '<p>Hi</p><img src="/admin/emails/attachment/inbox/abc/def" alt="sig" width="120"><script>alert(1)</script>';
    $clean = MailMessageDto::sanitizeHtmlForDisplay($raw);

    expect($clean)->toContain('src="/admin/emails/attachment/inbox/abc/def"')
        ->and($clean)->toContain('alt="sig"')
        ->and($clean)->not->toContain('<script')
        ->and($clean)->not->toContain('alert(1)');
});

test('mail message dto rewrites outlook style cid references', function () {
    $dto = MailMessageDto::fromGraph([
        'id' => 'msg-outlook',
        'subject' => 'Signature image',
        'body' => ['contentType' => 'html', 'content' => '<img src="cid:image001.png@01DC1234.ABC">'],
        'from' => ['emailAddress' => ['address' => 'sender@example.com', 'name' => 'Sender']],
        'toRecipients' => [['emailAddress' => ['address' => 'credentialing@revantagehbs.com']]],
        'receivedDateTime' => now()->toIso8601String(),
        'hasAttachments' => false,
        'attachments' => [[
            '@odata.type' => '#microsoft.graph.fileAttachment',
            'id' => 'att-sig',
            'name' => 'image001.png',
            'contentType' => 'image/png',
            'contentId' => '<image001.png@01DC1234.ABC>',
            'isInline' => true,
        ]],
    ], 'inbox', 'credentialing@revantagehbs.com');

    $html = $dto->bodyHtmlWithInlineAttachments('inbox', MailMessageDto::encodeGraphId('msg-outlook'));

    expect($html)->toContain('/admin/emails/attachment/inbox/')
        ->and($html)->not->toContain('cid:image001.png');
});

test('graph getMessage loads attachments when html body contains cid references', function () {
    configureGraphForTests();

    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $url = $request->url();

        if (str_contains($url, 'login.microsoftonline.com')) {
            return Http::response(['access_token' => 'fake-graph-token', 'expires_in' => 3600], 200);
        }

        if (str_contains($url, '/messages/msg-cid/attachments')) {
            return Http::response([
                'value' => [[
                    '@odata.type' => '#microsoft.graph.fileAttachment',
                    'id' => 'att-inline',
                    'name' => 'sig.png',
                    'contentType' => 'image/png',
                    'contentId' => 'sig@inline',
                    'isInline' => true,
                ]],
            ], 200);
        }

        if (str_contains($url, '/messages/msg-cid')) {
            return Http::response([
                'id' => 'msg-cid',
                'subject' => 'Inline only',
                'body' => ['contentType' => 'html', 'content' => '<img src="cid:sig@inline">'],
                'from' => ['emailAddress' => ['address' => 'provider@example.com', 'name' => 'Provider']],
                'toRecipients' => [['emailAddress' => ['address' => 'credentialing@revantagehbs.com']]],
                'receivedDateTime' => now()->toIso8601String(),
                'hasAttachments' => false,
                'conversationId' => 'conv-cid',
                'internetMessageId' => '<cid@example.com>',
                'internetMessageHeaders' => [],
                'isRead' => true,
            ], 200);
        }

        return Http::response(['value' => []], 200);
    });

    $message = app(GraphMailboxService::class)->getMessage('msg-cid', 'inbox', true);

    expect($message->attachments)->toHaveCount(1)
        ->and($message->attachments[0]['contentId'])->toBe('sig@inline');
});

test('graph downloadAttachment retrieves raw binary via value endpoint', function () {
    configureGraphForTests();

    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $xlsxBytes = "PK\x03\x04".str_repeat('x', 100);

    Http::fake(function (\Illuminate\Http\Client\Request $request) use ($pngBytes, $xlsxBytes) {
        $url = $request->url();

        if (str_contains($url, 'login.microsoftonline.com')) {
            return Http::response(['access_token' => 'fake-graph-token', 'expires_in' => 3600], 200);
        }

        if (str_contains($url, 'att-png') && (str_contains($url, '$value') || str_contains($url, '%24value'))) {
            return Http::response($pngBytes, 200, ['Content-Type' => 'image/png']);
        }

        if (str_contains($url, 'att-xlsx') && (str_contains($url, '$value') || str_contains($url, '%24value'))) {
            return Http::response($xlsxBytes, 200, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        }

        if (str_contains($url, 'att-png')) {
            return Http::response([
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'id' => 'att-png',
                'name' => 'logo.png',
                'contentType' => 'image/png',
                'size' => strlen($pngBytes),
            ], 200);
        }

        if (str_contains($url, 'att-xlsx')) {
            return Http::response([
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'id' => 'att-xlsx',
                'name' => 'data.xlsx',
                'contentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'size' => strlen($xlsxBytes),
            ], 200);
        }

        return Http::response(['value' => []], 200);
    });

    $service = app(GraphMailboxService::class);
    $png = $service->downloadAttachment('msg-1', 'att-png');
    $xlsx = $service->downloadAttachment('msg-1', 'att-xlsx');

    expect($png['content'])->toBe($pngBytes)
        ->and($xlsx['content'])->toBe($xlsxBytes)
        ->and(str_starts_with($xlsx['content'], 'PK'))->toBeTrue()
        ->and($png['contentType'])->toBe('image/png');
});

test('graph downloadAttachment prefers valid contentBytes for xlsx files', function () {
    configureGraphForTests();

    $xlsxBytes = "PK\x03\x04".str_repeat('sheet', 40);
    $valueCalls = 0;

    Http::fake(function (\Illuminate\Http\Client\Request $request) use ($xlsxBytes, &$valueCalls) {
        $url = $request->url();

        if (str_contains($url, 'login.microsoftonline.com')) {
            return Http::response(['access_token' => 'fake-graph-token', 'expires_in' => 3600], 200);
        }

        if (str_contains($url, '$value') || str_contains($url, '%24value')) {
            $valueCalls++;

            return Http::response('CORRUPT', 200, ['Content-Type' => 'application/octet-stream']);
        }

        return Http::response([
            '@odata.type' => '#microsoft.graph.fileAttachment',
            'id' => 'att-xlsx-bytes',
            'name' => 'The Anxiety Center 20260730.xlsx',
            'contentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'contentBytes' => base64_encode($xlsxBytes),
            'size' => strlen($xlsxBytes),
        ], 200);
    });

    $file = app(GraphMailboxService::class)->downloadAttachment('msg-1', 'att-xlsx-bytes');

    expect($file['content'])->toBe($xlsxBytes)
        ->and(str_starts_with($file['content'], 'PK'))->toBeTrue()
        ->and($valueCalls)->toBe(0);
});

test('graph downloadAttachment falls back to value when contentBytes is corrupt for xlsx', function () {
    configureGraphForTests();

    $xlsxBytes = "PK\x03\x04".str_repeat('ok', 50);

    Http::fake(function (\Illuminate\Http\Client\Request $request) use ($xlsxBytes) {
        $url = $request->url();

        if (str_contains($url, 'login.microsoftonline.com')) {
            return Http::response(['access_token' => 'fake-graph-token', 'expires_in' => 3600], 200);
        }

        if (str_contains($url, '$value') || str_contains($url, '%24value')) {
            return Http::response($xlsxBytes, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        }

        return Http::response([
            '@odata.type' => '#microsoft.graph.fileAttachment',
            'id' => 'att-xlsx-bad',
            'name' => 'report.xlsx',
            'contentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'contentBytes' => base64_encode('not-a-zip-file'),
            'size' => 12,
        ], 200);
    });

    $file = app(GraphMailboxService::class)->downloadAttachment('msg-1', 'att-xlsx-bad');

    expect($file['content'])->toBe($xlsxBytes)
        ->and(str_starts_with($file['content'], 'PK'))->toBeTrue();
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
