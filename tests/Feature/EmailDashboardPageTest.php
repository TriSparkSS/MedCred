<?php

use App\Data\SentEmailResult;
use App\Livewire\Admin\Email\EmailDashboardPage;
use App\Models\Admin;
use App\Services\CredentialingEmailService;
use App\Services\GraphMailboxService;
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

    $this->mock(GraphMailboxService::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturn(false);
        $mock->shouldReceive('normalizeFolder')->andReturnUsing(fn ($f) => $f === 'sent' ? 'sentitems' : 'inbox');
    });

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

    $this->mock(GraphMailboxService::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturn(false);
        $mock->shouldReceive('normalizeFolder')->andReturnUsing(fn ($f) => $f === 'sent' ? 'sentitems' : 'inbox');
    });

    Livewire::actingAs($admin, 'admin')
        ->test(EmailDashboardPage::class)
        ->call('openComposeModal')
        ->assertSet('showComposeModal', true)
        ->call('closeComposeModal')
        ->assertSet('showComposeModal', false);
});

test('send email does not error when optional ids are empty strings', function () {
    $admin = Admin::where('username', 'superadmin')->first();

    $this->mock(GraphMailboxService::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturn(false);
        $mock->shouldReceive('normalizeFolder')->andReturnUsing(fn ($f) => $f === 'sent' ? 'sentitems' : 'inbox');
        $mock->shouldReceive('clearCache')->andReturnNull();
    });

    $this->mock(CredentialingEmailService::class, function ($mock) {
        $mock->shouldReceive('stats')->andReturn([
            'total_sent' => 0,
            'inbox_count' => 0,
            'graph_configured' => false,
        ]);
        $mock->shouldReceive('send')
            ->once()
            ->andReturn(new SentEmailResult(true, 'id@example.com'));
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
        ->assertSet('filter', 'sent');
});

test('failed compose send keeps modal open', function () {
    $admin = Admin::where('username', 'superadmin')->first();

    $this->mock(GraphMailboxService::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturn(false);
        $mock->shouldReceive('normalizeFolder')->andReturnUsing(fn ($f) => $f === 'sent' ? 'sentitems' : 'inbox');
    });

    $this->mock(CredentialingEmailService::class, function ($mock) {
        $mock->shouldReceive('stats')->andReturn([
            'total_sent' => 0,
            'inbox_count' => 0,
            'graph_configured' => false,
        ]);
        $mock->shouldReceive('send')
            ->once()
            ->andReturn(new SentEmailResult(false, 'id@example.com', 'SMTP failed'));
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
