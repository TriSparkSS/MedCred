<?php

use App\Livewire\Admin\Email\EmailDashboardPage;
use App\Models\Admin;
use App\Models\EmailMessage;
use App\Services\CredentialingEmailService;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AdminPermissionSeeder::class);
    $this->seed(AdminSeeder::class);
});

test('compose modal opens with empty fields', function () {
    $admin = Admin::where('username', 'superadmin')->first();

    Livewire::actingAs($admin, 'admin')
        ->test(EmailDashboardPage::class)
        ->call('openComposeModal')
        ->assertSet('showComposeModal', true)
        ->assertSet('composeTo', '')
        ->assertSet('composeSubject', '')
        ->assertSet('composeBody', '');
});

test('compose modal closes cleanly', function () {
    $admin = Admin::where('username', 'superadmin')->first();

    Livewire::actingAs($admin, 'admin')
        ->test(EmailDashboardPage::class)
        ->call('openComposeModal')
        ->assertSet('showComposeModal', true)
        ->call('closeComposeModal')
        ->assertSet('showComposeModal', false);
});

test('send email does not error when optional ids are empty strings', function () {
    $admin = Admin::where('username', 'superadmin')->first();

    $sentMessage = new EmailMessage([
        'status' => 'sent',
        'subject' => 'Test Subject',
        'to_address' => 'test@example.com',
    ]);

    $this->mock(CredentialingEmailService::class, function ($mock) use ($sentMessage) {
        $mock->shouldReceive('stats')->andReturn([
            'total_sent' => 0,
            'inbox_count' => 0,
            'pending_replies' => 0,
            'unlinked_inbound' => 0,
            'bounced_failed' => 0,
            'reminders_sent' => 0,
        ]);
        $mock->shouldReceive('send')
            ->once()
            ->andReturn($sentMessage);
    });

    Livewire::actingAs($admin, 'admin')
        ->test(EmailDashboardPage::class)
        ->call('openComposeModal')
        ->set('composeTo', 'test@example.com')
        ->set('composeSubject', 'Test Subject')
        ->set('composeBody', 'Test body')
        ->set('composeCaseId', '')
        ->set('composeTemplateId', '')
        ->call('sendEmail')
        ->assertSet('showComposeModal', false)
        ->assertSet('filter', 'sent')
        ->assertSet('search', '');
});

test('failed compose send keeps modal open and switches to failed filter', function () {
    $admin = Admin::where('username', 'superadmin')->first();

    $failedMessage = new EmailMessage([
        'status' => 'failed',
        'error_message' => 'SMTP auth failed',
        'subject' => 'Test Subject',
        'to_address' => 'test@example.com',
    ]);

    $this->mock(CredentialingEmailService::class, function ($mock) use ($failedMessage) {
        $mock->shouldReceive('stats')->andReturn([
            'total_sent' => 0,
            'inbox_count' => 0,
            'pending_replies' => 0,
            'unlinked_inbound' => 0,
            'bounced_failed' => 0,
            'reminders_sent' => 0,
        ]);
        $mock->shouldReceive('send')
            ->once()
            ->andReturn($failedMessage);
    });

    Livewire::actingAs($admin, 'admin')
        ->test(EmailDashboardPage::class)
        ->set('search', 'hidden-term')
        ->call('openComposeModal')
        ->set('composeTo', 'test@example.com')
        ->set('composeSubject', 'Test Subject')
        ->set('composeBody', 'Test body')
        ->call('sendEmail')
        ->assertSet('showComposeModal', true)
        ->assertSet('filter', 'failed')
        ->assertSet('search', '');
});

test('compose send surfaces configuration exceptions', function () {
    $admin = Admin::where('username', 'superadmin')->first();

    $this->mock(CredentialingEmailService::class, function ($mock) {
        $mock->shouldReceive('stats')->andReturn([
            'total_sent' => 0,
            'inbox_count' => 0,
            'pending_replies' => 0,
            'unlinked_inbound' => 0,
            'bounced_failed' => 0,
            'reminders_sent' => 0,
        ]);
        $mock->shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('SMTP is not configured.'));
    });

    Livewire::actingAs($admin, 'admin')
        ->test(EmailDashboardPage::class)
        ->call('openComposeModal')
        ->set('composeTo', 'test@example.com')
        ->set('composeSubject', 'Test Subject')
        ->set('composeBody', 'Test body')
        ->call('sendEmail')
        ->assertSet('showComposeModal', true);
});

test('reset filters clears search and sets filter to all', function () {
    $admin = Admin::where('username', 'superadmin')->first();

    Livewire::actingAs($admin, 'admin')
        ->test(EmailDashboardPage::class)
        ->set('filter', 'sent')
        ->set('search', 'test query')
        ->call('resetFilters')
        ->assertSet('filter', 'all')
        ->assertSet('search', '');
});

test('setFilter updates active filter', function () {
    $admin = Admin::where('username', 'superadmin')->first();

    Livewire::actingAs($admin, 'admin')
        ->test(EmailDashboardPage::class)
        ->call('setFilter', 'inbox')
        ->assertSet('filter', 'inbox');
});

test('compose shows permission error for users without send access', function () {
    $admin = Admin::create([
        'name' => 'View Only',
        'username' => 'viewonly',
        'email' => 'viewonly@test.com',
        'phone' => '0000000099',
        'status' => 'active',
        'password' => 'password',
    ]);
    $admin->givePermissionTo('admin.emails.view');

    Livewire::actingAs($admin, 'admin')
        ->test(EmailDashboardPage::class)
        ->call('openComposeModal')
        ->assertSet('showComposeModal', false);
});
