<?php

use App\Livewire\Admin\Practices\PracticeEditPage;
use App\Livewire\Admin\Provider\ProviderEditPage;
use App\Livewire\Admin\Setting\PortalAccountManager;
use App\Livewire\Admin\Task\TaskBoardPage;
use App\Models\Admin;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\User;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(AdminPermissionSeeder::class);
    $this->seed(AdminSeeder::class);
    $this->seed(MasterDataSeeder::class);
    $this->seed(RoleSeeder::class);
});

function portalSettingsPractice(string $code = 'PAC'): Practice
{
    return Practice::create([
        'legal_name' => 'Portal Account Practice '.$code,
        'client_code' => $code,
        'email' => strtolower($code).'@practice.example',
        'status' => 'active',
        'user_id' => User::create([
            'name' => $code.' Practice User',
            'email' => strtolower($code).'@practice.example',
            'password' => 'old-password',
        ])->id,
    ]);
}

function portalSettingsProvider(Practice $practice): ProviderDetails
{
    $provider = ProviderDetails::create([
        'user_id' => User::create([
            'name' => 'Dr. Portal '.$practice->client_code,
            'email' => 'dr-portal-'.strtolower($practice->client_code).'@example.com',
            'password' => 'old-password',
        ])->id,
        'npi' => (string) random_int(1000000000, 1999999999),
        'status' => 'active',
        'practice' => $practice->legal_name,
    ]);

    $provider->practices()->syncWithoutDetaching([$practice->id => ['primary_flag' => true]]);

    return $provider;
}

test('portal accounts live in admin settings and can reset practice and provider passwords', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = portalSettingsPractice('SET');
    $provider = portalSettingsProvider($practice);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.settings.portal-accounts'))
        ->assertOk()
        ->assertSee('Portal Accounts')
        ->assertSee($practice->legal_name)
        ->assertSee($provider->user->name);

    Livewire::actingAs($admin, 'admin')
        ->test(PortalAccountManager::class)
        ->assertSee($practice->legal_name)
        ->assertSee($provider->user->email)
        ->set('filterType', 'practice')
        ->assertSee($practice->legal_name)
        ->assertDontSee($provider->user->email)
        ->set('filterType', '')
        ->set('search', $practice->client_code)
        ->assertSee($practice->legal_name);

    Livewire::actingAs($admin, 'admin')
        ->test(PortalAccountManager::class)
        ->call('openEditModal', $practice->user_id)
        ->set('userPassword', 'NewPortal99')
        ->set('userEmail', 'set-updated@practice.example')
        ->call('save')
        ->assertHasNoErrors();

    expect(Hash::check('NewPortal99', $practice->user->fresh()->password))->toBeTrue()
        ->and($practice->fresh()->email)->toBe('set-updated@practice.example')
        ->and($practice->user->fresh()->email)->toBe('set-updated@practice.example');

    Livewire::actingAs($admin, 'admin')
        ->test(PortalAccountManager::class)
        ->call('openEditModal', $provider->user_id)
        ->set('userPassword', 'ProviderPortal99')
        ->call('save')
        ->assertHasNoErrors();

    expect(Hash::check('ProviderPortal99', $provider->user->fresh()->password))->toBeTrue();
});

test('practice and provider edit pages no longer host portal password fields', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = portalSettingsPractice('EDT');
    $provider = portalSettingsProvider($practice);

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeEditPage::class, ['practice' => $practice->id])
        ->assertDontSee('New Password')
        ->assertDontSeeHtml('wire:model="userData.password"')
        ->assertSee('Manage Portal Accounts');

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderEditPage::class, ['provider' => $provider->id])
        ->assertDontSeeHtml('wire:model="userData.password"')
        ->assertSee('Manage passwords in Admin Settings');
});

test('billing cannot open portal accounts settings', function () {
    $billing = Admin::where('username', 'billing')->firstOrFail();

    $this->actingAs($billing, 'admin')
        ->get(route('admin.settings.portal-accounts'))
        ->assertForbidden();
});

test('task allocation filter lists follow-up task type', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();

    Livewire::actingAs($admin, 'admin')
        ->test(TaskBoardPage::class)
        ->assertSee('Follow-up')
        ->assertSeeHtml('value="follow_up"');
});
