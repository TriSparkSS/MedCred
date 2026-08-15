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
            'has' => ['admin.credentials.view', 'admin.credentials.edit', 'admin.emails.view', 'admin.emails.send', 'admin.tasks.view', 'admin.tasks.manage', 'admin.documents.view'],
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
