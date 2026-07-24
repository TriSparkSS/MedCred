<?php

use App\Models\EmailMessage;
use App\Services\CredentialingEmailService;
use App\Services\ImapMailboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('logOutboundFromImap creates outbound sent message', function () {
    $message = app(CredentialingEmailService::class)->logOutboundFromImap([
        'from_address' => 'credentialing@revantagehbs.com',
        'to_address' => 'recipient@example.com',
        'subject' => 'Imported sent mail',
        'body' => 'Body from Sent Items folder',
        'message_id' => '<abc-123@revantagehbs.com>',
        'sent_at' => now(),
    ]);

    expect($message->direction)->toBe('outbound')
        ->and($message->status)->toBe('sent')
        ->and($message->queue_category)->toBe('sent')
        ->and($message->sent_at)->not->toBeNull();
});

test('isDuplicate matches message ids with or without angle brackets', function () {
    EmailMessage::create([
        'direction' => 'outbound',
        'from_address' => 'credentialing@revantagehbs.com',
        'to_address' => 'recipient@example.com',
        'subject' => 'Already sent',
        'body' => 'Body',
        'status' => 'sent',
        'queue_category' => 'sent',
        'message_id' => 'abc-123@revantagehbs.com',
        'sent_at' => now(),
    ]);

    $service = app(ImapMailboxService::class);
    $method = new ReflectionMethod($service, 'isDuplicate');
    $method->setAccessible(true);

    $isDuplicate = $method->invoke($service, [
        'message_id' => '<abc-123@revantagehbs.com>',
    ]);

    expect($isDuplicate)->toBeTrue();
});

test('mail settings store separate uid keys for inbox and sent folders', function () {
    $service = app(\App\Services\MailSettingsService::class);

    $service->setImapLastSyncForFolder(\App\Services\MailSettingsService::KEY_IMAP_LAST_UID, 42);
    $service->setImapLastSyncForFolder(\App\Services\MailSettingsService::KEY_IMAP_SENT_LAST_UID, 99);

    $settings = $service->getSettings();

    expect($settings['imap_last_uid'])->toBe(42)
        ->and($settings['imap_sent_last_uid'])->toBe(99);
});

test('formatImapError adds office365 guidance for authenticate failures', function () {
    $service = app(\App\Services\MailSettingsService::class);
    $error = new RuntimeException('NO AUTHENTICATE failed.');

    $formatted = $service->formatImapError($error);

    expect($service->isImapAuthFailure('NO AUTHENTICATE failed.'))->toBeTrue()
        ->and($formatted)->toContain('Microsoft 365')
        ->and($formatted)->toContain('Microsoft Graph OAuth');
});
