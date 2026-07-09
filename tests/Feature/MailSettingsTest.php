<?php

use App\Livewire\Admin\Setting\MailSettingsManager;
use App\Mail\CredentialingTemplateMail;
use App\Models\Admin;
use App\Services\MailSettingsService;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AdminPermissionSeeder::class);
    $this->seed(AdminSeeder::class);
});

function seedMailSettingsWithPassword(): void
{
    app(MailSettingsService::class)->saveSettings([
        'enabled' => true,
        'host' => 'smtp.office365.com',
        'port' => 587,
        'encryption' => 'tls',
        'scheme' => 'smtp',
        'username' => 'credentialing@revantagehbs.com',
        'password' => 'test-smtp-password',
        'from_address' => 'credentialing@revantagehbs.com',
        'from_name' => 'Revantage Credentialing',
        'imap_enabled' => false,
        'imap_host' => 'outlook.office365.com',
        'imap_port' => 993,
        'imap_encryption' => 'ssl',
        'imap_folder' => 'INBOX',
        'imap_username' => 'credentialing@revantagehbs.com',
    ]);
}

test('send test email uses resolved from address when env from is empty', function () {
    Mail::fake();
    seedMailSettingsWithPassword();

    config(['mail.from.address' => '', 'mail.from.name' => '']);

    $admin = Admin::where('username', 'superadmin')->first();

    Livewire::actingAs($admin, 'admin')
        ->test(MailSettingsManager::class)
        ->set('testEmailTo', 'recipient@example.com')
        ->call('sendTest')
        ->assertHasNoErrors();

    Mail::assertSent(CredentialingTemplateMail::class, function (CredentialingTemplateMail $mail) {
        return $mail->fromAddress === 'credentialing@revantagehbs.com'
            && filled($mail->fromAddress)
            && filled($mail->messageId)
            && ! str_contains($mail->messageId, '<')
            && str_ends_with($mail->messageId, '@revantagehbs.com');
    });
});

test('generated message id uses sender domain when app url is localhost', function () {
    seedMailSettingsWithPassword();
    config(['app.url' => 'http://localhost']);

    $service = app(\App\Services\CredentialingEmailService::class);
    $method = new ReflectionMethod($service, 'generateMessageId');
    $method->setAccessible(true);
    $messageId = $method->invoke($service);

    expect($messageId)
        ->not->toContain('<')
        ->not->toContain('>')
        ->toEndWith('@revantagehbs.com');
});

test('credentialing template mail strips angle brackets from message id header', function () {
    $mail = new CredentialingTemplateMail(
        'Subject',
        'Body',
        '<abc-123@example.com>',
    );

    $headers = $mail->headers();

    expect($headers->messageId)->toBe('abc-123@example.com');
});

test('send test email validates from address before sending', function () {
    Mail::fake();

    $admin = Admin::where('username', 'superadmin')->first();

    Livewire::actingAs($admin, 'admin')
        ->test(MailSettingsManager::class)
        ->set('host', 'smtp.office365.com')
        ->set('port', 587)
        ->set('encryption', 'tls')
        ->set('scheme', 'smtp')
        ->set('username', 'credentialing@revantagehbs.com')
        ->set('password', 'test-smtp-password')
        ->set('from_address', '')
        ->set('from_name', 'Revantage Credentialing')
        ->set('testEmailTo', 'recipient@example.com')
        ->call('sendTest')
        ->assertHasErrors(['from_address']);

    Mail::assertNothingSent();
});

test('resolveFromAddress falls back to smtp username when from address is blank', function () {
    $service = app(MailSettingsService::class);

    expect($service->resolveFromAddress([
        'from_address' => '',
        'username' => 'credentialing@revantagehbs.com',
    ]))->toBe('credentialing@revantagehbs.com');
});
