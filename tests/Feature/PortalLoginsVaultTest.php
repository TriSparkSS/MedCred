<?php

use App\Livewire\Admin\Portal\PortalLoginsSection;
use App\Livewire\Admin\Practices\PracticeDetailsPage;
use App\Livewire\Admin\Practices\PracticeEditPage;
use App\Livewire\Admin\Provider\ProviderDetailsPage;
use App\Livewire\Admin\Provider\ProviderEditPage;
use App\Models\Admin;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\User;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

function vaultPractice(string $code = 'VLT'): Practice
{
    return Practice::create([
        'legal_name' => 'Vault Practice '.$code,
        'client_code' => $code,
        'email' => strtolower($code).'@vault.example',
        'status' => 'active',
        'user_id' => User::create([
            'name' => $code.' Vault User',
            'email' => strtolower($code).'-vault-'.uniqid().'@example.com',
            'password' => 'password',
        ])->id,
    ]);
}

function vaultProvider(Practice $practice): ProviderDetails
{
    $provider = ProviderDetails::create([
        'user_id' => User::create([
            'name' => 'Dr. Vault',
            'email' => 'dr-vault-'.uniqid().'@example.com',
            'password' => 'password',
        ])->id,
        'npi' => (string) random_int(1000000000, 1999999999),
        'status' => 'active',
        'practice' => $practice->legal_name,
    ]);

    $provider->practices()->syncWithoutDetaching([$practice->id => ['primary_flag' => true]]);

    return $provider;
}

test('practice details and edit show a separate practice logins section', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = vaultPractice('PLP');

    $this->actingAs($admin, 'admin')
        ->get(route('admin.practices.show', $practice))
        ->assertOk()
        ->assertSee('Practice logins')
        ->assertSee('MFA Email')
        ->assertSee('MFA Phone')
        ->assertSee('Security and Account Details');

    $this->actingAs($admin, 'admin')
        ->get(route('admin.practices.edit', $practice))
        ->assertOk()
        ->assertSee('Practice logins')
        ->assertSee('Security and Account Details');

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeDetailsPage::class, ['practice' => $practice->id])
        ->assertSeeLivewire(PortalLoginsSection::class)
        ->assertSee('Practice logins');

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeEditPage::class, ['practice' => $practice->id])
        ->assertSeeLivewire(PortalLoginsSection::class)
        ->assertSee('Practice logins');
});

test('provider details and edit show a separate provider logins section', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = vaultPractice('PRP');
    $provider = vaultProvider($practice);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.providers.show', $provider))
        ->assertOk()
        ->assertSee('Provider logins')
        ->assertSee('MFA Email')
        ->assertSee('MFA Phone')
        ->assertSee('Security and Account Details');

    $this->actingAs($admin, 'admin')
        ->get(route('admin.providers.edit', $provider))
        ->assertOk()
        ->assertSee('Provider logins')
        ->assertSee('Security and Account Details');

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderDetailsPage::class, ['provider' => $provider])
        ->assertSeeLivewire(PortalLoginsSection::class)
        ->assertSee('Provider logins');

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderEditPage::class, ['provider' => $provider->id])
        ->assertSeeLivewire(PortalLoginsSection::class)
        ->assertSee('Provider logins');
});

test('practice portal login persists password mfa and security details', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = vaultPractice('PLC');

    Livewire::actingAs($admin, 'admin')
        ->test(PortalLoginsSection::class, ['ownerType' => 'practice', 'ownerId' => $practice->id])
        ->call('openCreateModal')
        ->assertSee('MFA Email')
        ->assertSee('Email associated with the account')
        ->assertSee('MFA Phone')
        ->assertSee('Phone number associated with the account')
        ->assertSee('Security and Account Details')
        ->assertSee('Portal URL')
        ->assertSee('Security questions')
        ->assertSee('Account numbers and notes')
        ->assertDontSeeHtml('wire:submit')
        ->assertSeeHtml('wire:click.stop="save"')
        ->set('formData.portal_name', 'Availity')
        ->set('formData.username', 'practice-availity')
        ->set('formData.password', 'PracticeVault99!')
        ->set('formData.mfa_email', 'practice-mfa@example.com')
        ->set('formData.mfa_phone', '555-0200')
        ->set('formData.portal_url', 'https://essentials.availity.com')
        ->set('formData.security_notes', 'First pet was Sparky.')
        ->set('formData.account_details', 'Org NPI 1234567890')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Availity')
        ->assertSee('practice-availity')
        ->assertSee('practice-mfa@example.com')
        ->assertSee('555-0200')
        ->assertSee('https://essentials.availity.com')
        ->assertSee('First pet was Sparky.')
        ->assertSee('Org NPI 1234567890')
        ->assertSee('Security and Account Details')
        ->assertDontSee('PracticeVault99!');

    $login = $practice->portalLogins()->firstOrFail();
    expect($login->getRawOriginal('password'))->not->toBe('PracticeVault99!')
        ->and($login->makeVisible('password')->password)->toBe('PracticeVault99!')
        ->and($login->mfa_email)->toBe('practice-mfa@example.com')
        ->and($login->mfa_phone)->toBe('555-0200')
        ->and($login->portal_url)->toBe('https://essentials.availity.com')
        ->and($login->security_notes)->toBe('First pet was Sparky.')
        ->and($login->account_details)->toBe('Org NPI 1234567890');
});

test('provider portal login persists password mfa and security details', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = vaultPractice('PRC');
    $provider = vaultProvider($practice);

    Livewire::actingAs($admin, 'admin')
        ->test(PortalLoginsSection::class, ['ownerType' => 'provider', 'ownerId' => $provider->id])
        ->call('openCreateModal')
        ->set('formData.portal_name', 'CAQH')
        ->set('formData.username', 'caqh-vault')
        ->set('formData.password', 'ProviderVault99!')
        ->set('formData.mfa_email', 'provider-mfa@example.com')
        ->set('formData.mfa_phone', '555-0100')
        ->set('formData.portal_url', 'https://proview.caqh.org')
        ->set('formData.security_notes', 'Mother maiden name on file.')
        ->set('formData.account_details', 'CAQH ID 998877')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('CAQH')
        ->assertSee('caqh-vault')
        ->assertSee('provider-mfa@example.com')
        ->assertSee('555-0100')
        ->assertSee('https://proview.caqh.org')
        ->assertSee('Mother maiden name on file.')
        ->assertSee('CAQH ID 998877')
        ->assertDontSee('ProviderVault99!');

    $login = $provider->portalLogins()->firstOrFail();
    expect($login->getRawOriginal('password'))->not->toBe('ProviderVault99!')
        ->and($login->makeVisible('password')->password)->toBe('ProviderVault99!')
        ->and($login->mfa_email)->toBe('provider-mfa@example.com')
        ->and($login->mfa_phone)->toBe('555-0100')
        ->and($login->portal_url)->toBe('https://proview.caqh.org')
        ->and($login->security_notes)->toBe('Mother maiden name on file.')
        ->and($login->account_details)->toBe('CAQH ID 998877');
});
