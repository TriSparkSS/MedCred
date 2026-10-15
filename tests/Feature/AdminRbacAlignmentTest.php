<?php

use App\Enums\AdminRole;
use App\Livewire\Admin\Credential\CredentialListPage;
use App\Livewire\Admin\Practices\PracticeListPage;
use App\Livewire\Admin\Provider\ProviderListPage;
use App\Models\Admin;
use App\Models\Practice;
use App\Models\User;
use App\Services\AdminScopeService;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(AdminPermissionSeeder::class);
    $this->seed(AdminSeeder::class);
});

function adminByUsername(string $username): Admin
{
    return Admin::where('username', $username)->firstOrFail();
}

test('permission matrix matches permission.jpeg roles', function () {
    $matrix = [
        'superadmin' => [
            'has' => ['admin.settings.manage', 'admin.users.manage', 'admin.portal-credentials.manage', 'admin.settings.mail', 'admin.providers.create', 'admin.providers.delete', 'admin.practices.manage', 'admin.practices.delete', 'admin.tasks.delete', 'admin.credentials.assign'],
            'missing' => [],
        ],
        'manager' => [
            'has' => ['admin.providers.create', 'admin.practices.manage', 'admin.practices.delete', 'admin.providers.delete', 'admin.tasks.delete', 'admin.credentials.assign', 'admin.tasks.assign', 'admin.delay.override', 'admin.emails.view', 'admin.tasks.view'],
            'missing' => ['admin.settings.manage', 'admin.users.manage', 'admin.portal-credentials.manage', 'admin.settings.mail'],
        ],
        'executive' => [
            'has' => ['admin.credentials.view', 'admin.credentials.edit', 'admin.emails.view', 'admin.emails.send', 'admin.tasks.view', 'admin.tasks.manage', 'admin.tasks.escalate', 'admin.documents.view'],
            'missing' => ['admin.providers.create', 'admin.providers.edit', 'admin.providers.delete', 'admin.practices.manage', 'admin.practices.delete', 'admin.tasks.delete', 'admin.portal-credentials.manage', 'admin.settings.manage', 'admin.settings.mail', 'admin.users.manage'],
        ],
        'billing' => [
            'has' => ['admin.dashboard.view', 'admin.providers.view', 'admin.practices.view', 'admin.credentials.view', 'admin.billing.view', 'admin.reports.view', 'admin.analytics.view'],
            'missing' => ['admin.credentials.edit', 'admin.providers.create', 'admin.practices.manage', 'admin.emails.send', 'admin.tasks.manage', 'admin.billing.notify', 'admin.portal-credentials.manage', 'admin.settings.mail'],
        ],
    ];

    foreach ($matrix as $username => $expectations) {
        $admin = adminByUsername($username);
        foreach ($expectations['has'] as $permission) {
            expect($admin->can($permission))
                ->toBeTrue();
        }
        foreach ($expectations['missing'] as $permission) {
            expect($admin->can($permission))
                ->toBeFalse();
        }
    }
});

test('scoped roles only see assigned practices', function () {
    $visible = Practice::create([
        'legal_name' => 'Assigned Practice',
        'client_code' => 'ASG',
        'email' => 'assigned@example.com',
        'status' => 'active',
        'user_id' => User::create([
            'name' => 'Assigned Practice User',
            'email' => 'assigned-practice@example.com',
            'password' => 'password',
        ])->id,
    ]);
    $hidden = Practice::create([
        'legal_name' => 'Hidden Practice',
        'client_code' => 'HID',
        'email' => 'hidden@example.com',
        'status' => 'active',
        'user_id' => User::create([
            'name' => 'Hidden Practice User',
            'email' => 'hidden-practice@example.com',
            'password' => 'password',
        ])->id,
    ]);

    $billing = adminByUsername('billing');
    $billing->practices()->sync([$visible->id]);

    $scope = app(AdminScopeService::class);
    expect($scope->canAccessPractice($billing, $visible->id))->toBeTrue();
    expect($scope->canAccessPractice($billing, $hidden->id))->toBeFalse();

    $superadmin = adminByUsername('superadmin');
    expect($scope->canAccessPractice($superadmin, $hidden->id))->toBeTrue();

    Livewire::actingAs($billing, 'admin')
        ->test(PracticeListPage::class)
        ->assertSee('Assigned Practice')
        ->assertDontSee('Hidden Practice');

    $this->actingAs($superadmin, 'admin')
        ->get(route('admin.settings.mail'))
        ->assertOk();

    $this->actingAs($billing, 'admin')
        ->get(route('admin.settings.mail'))
        ->assertForbidden();

    $this->actingAs($superadmin, 'admin')
        ->get(route('admin.settings.portal-accounts'))
        ->assertOk();

    $this->actingAs($billing, 'admin')
        ->get(route('admin.settings.portal-accounts'))
        ->assertForbidden();

    $this->actingAs(adminByUsername('executive'), 'admin')
        ->get(route('admin.providers.create'))
        ->assertForbidden();

    $this->actingAs(adminByUsername('manager'), 'admin')
        ->get(route('admin.providers.create'))
        ->assertOk();
});

test('billing cannot mutate credential tracker', function () {
    $billing = adminByUsername('billing');

    Livewire::actingAs($billing, 'admin')
        ->test(CredentialListPage::class)
        ->assertSet('canEditCredentials', false)
        ->assertSet('canAssignCredentials', false)
        ->assertSet('canEscalateTasks', false);
});

test('roleless admin is denied route access and is not unrestricted', function () {
    $roleless = Admin::create([
        'name' => 'Roleless Admin',
        'username' => 'roleless',
        'email' => 'roleless@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $scope = app(AdminScopeService::class);
    expect($scope->isUnrestricted($roleless))->toBeFalse()
        ->and($scope->isPracticeScoped($roleless))->toBeTrue()
        ->and($scope->assignedPracticeIds($roleless))->toBe([]);

    $this->actingAs($roleless, 'admin')
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('practice-scoped admin gets 403 on credential packet download for unassigned practice case', function () {
    $this->seed(\Database\Seeders\MasterDataSeeder::class);

    $assignedPractice = Practice::create([
        'legal_name' => 'Assigned Packet Practice',
        'client_code' => 'APP',
        'email' => 'assigned-packet@example.com',
        'status' => 'active',
        'user_id' => User::create([
            'name' => 'Assigned Packet User',
            'email' => 'assigned-packet-user@example.com',
            'password' => 'password',
        ])->id,
    ]);

    $unassignedPractice = Practice::create([
        'legal_name' => 'Unassigned Packet Practice',
        'client_code' => 'UPP',
        'email' => 'unassigned-packet@example.com',
        'status' => 'active',
        'user_id' => User::create([
            'name' => 'Unassigned Packet User',
            'email' => 'unassigned-packet-user@example.com',
            'password' => 'password',
        ])->id,
    ]);

    $provider = \App\Models\ProviderDetails::create([
        'user_id' => User::create([
            'name' => 'Dr. Packet',
            'email' => 'dr-packet@example.com',
            'password' => 'password',
        ])->id,
        'npi' => '1888888888',
        'status' => 'active',
        'practice' => $unassignedPractice->legal_name,
    ]);

    $payer = \App\Models\Payer::create(['name' => 'Packet Payer', 'is_active' => true]);
    $status = \App\Models\Status::firstOrFail();

    $unassignedCase = \App\Models\CredentialingCase::create([
        'practice_id' => $unassignedPractice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
        'state' => 'TX',
    ]);

    $executive = adminByUsername('executive');
    $executive->practices()->sync([$assignedPractice->id]);

    $this->actingAs($executive, 'admin')
        ->get(route('admin.credentials.packet', $unassignedCase))
        ->assertForbidden();
});

test('practice-scoped admin cannot view another practice task in TaskDetailDrawer', function () {
    $this->seed(\Database\Seeders\MasterDataSeeder::class);

    $assignedPractice = Practice::create([
        'legal_name' => 'Assigned Task Practice',
        'client_code' => 'ATP',
        'email' => 'assigned-task@example.com',
        'status' => 'active',
        'user_id' => User::create([
            'name' => 'Assigned Task User',
            'email' => 'assigned-task-user@example.com',
            'password' => 'password',
        ])->id,
    ]);

    $unassignedPractice = Practice::create([
        'legal_name' => 'Unassigned Task Practice',
        'client_code' => 'UTP',
        'email' => 'unassigned-task@example.com',
        'status' => 'active',
        'user_id' => User::create([
            'name' => 'Unassigned Task User',
            'email' => 'unassigned-task-user@example.com',
            'password' => 'password',
        ])->id,
    ]);

    $provider = \App\Models\ProviderDetails::create([
        'user_id' => User::create([
            'name' => 'Dr. Unassigned Task',
            'email' => 'dr-unassigned-task@example.com',
            'password' => 'password',
        ])->id,
        'npi' => '1999999999',
        'status' => 'active',
        'practice' => $unassignedPractice->legal_name,
    ]);
    $provider->practices()->sync([$unassignedPractice->id => ['primary_flag' => true]]);

    $payer = \App\Models\Payer::create(['name' => 'Task Payer', 'is_active' => true]);
    $status = \App\Models\Status::firstOrFail();

    $unassignedCase = \App\Models\CredentialingCase::create([
        'practice_id' => $unassignedPractice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
        'state' => 'TX',
    ]);

    $otherTask = \App\Models\Task::create([
        'title' => 'Secret Unassigned Task',
        'credentialing_case_id' => $unassignedCase->id,
        'provider_id' => $provider->id,
        'status' => \App\Enums\TaskStatus::Open,
    ]);

    $executive = adminByUsername('executive');
    $executive->practices()->sync([$assignedPractice->id]);

    Livewire::actingAs($executive, 'admin')
        ->test(\App\Livewire\Admin\Task\TaskDetailDrawer::class)
        ->call('open', $otherTask->id)
        ->assertForbidden()
        ->assertDontSee('Secret Unassigned Task');
});
