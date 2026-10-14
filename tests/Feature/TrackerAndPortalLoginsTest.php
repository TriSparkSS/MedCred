<?php

use App\Livewire\Admin\Credential\CredentialCreatePage;
use App\Livewire\Admin\Credential\CredentialListPage;
use App\Livewire\Admin\Documents\DocumentListPage;
use App\Livewire\Admin\Portal\PortalLoginsSection;
use App\Livewire\Admin\Practices\PracticeDetailsPage;
use App\Livewire\Admin\Practices\PracticeEditPage;
use App\Livewire\Admin\Provider\ProviderDetailsPage;
use App\Livewire\Admin\Provider\ProviderEditPage;
use App\Models\Admin;
use App\Models\CredentialingCase;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\Status;
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

function trackerPractice(string $code = 'TRK'): Practice
{
    return Practice::create([
        'legal_name' => 'Tracker Practice '.$code,
        'client_code' => $code,
        'email' => strtolower($code).'@example.com',
        'status' => 'active',
        'user_id' => User::create([
            'name' => $code.' User',
            'email' => strtolower($code).'-user-'.uniqid().'@example.com',
            'password' => 'password',
        ])->id,
    ]);
}

function trackerProvider(Practice $practice): ProviderDetails
{
    $provider = ProviderDetails::create([
        'user_id' => User::create([
            'name' => 'Dr. Tracker',
            'email' => 'dr-tracker-'.uniqid().'@example.com',
            'password' => 'password',
        ])->id,
        'npi' => (string) random_int(1000000000, 1999999999),
        'status' => 'active',
        'practice' => $practice->legal_name,
    ]);

    $provider->practices()->syncWithoutDetaching([$practice->id => ['primary_flag' => true]]);

    return $provider;
}

test('creating an application with a typed payer name creates or reuses the payer', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = trackerPractice('PAY');
    $provider = trackerProvider($practice);
    $statusId = Status::where('name', 'Not Started')->firstOrFail()->id;

    Livewire::actingAs($admin, 'admin')
        ->test(CredentialCreatePage::class)
        ->set('formData.provider_id', $provider->id)
        ->set('formData.practice_id', $practice->id)
        ->set('formData.status_id', $statusId)
        ->set('payerName', 'Typed Payer Plan')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.applications'));

    $payer = Payer::where('name', 'Typed Payer Plan')->firstOrFail();
    expect($payer->is_active)->toBeTrue();
    expect(CredentialingCase::where('payer_id', $payer->id)->where('provider_id', $provider->id)->exists())->toBeTrue();

    $existing = Payer::create(['name' => 'Reuse Payer', 'is_active' => true]);

    Livewire::actingAs($admin, 'admin')
        ->test(CredentialCreatePage::class)
        ->set('formData.provider_id', $provider->id)
        ->set('formData.practice_id', $practice->id)
        ->set('formData.status_id', $statusId)
        ->set('formData.state', 'CA')
        ->set('payerName', 'reuse payer')
        ->call('save')
        ->assertHasNoErrors();

    expect(Payer::whereRaw('LOWER(name) = ?', ['reuse payer'])->count())->toBe(1)
        ->and(CredentialingCase::where('payer_id', $existing->id)->exists())->toBeTrue();
});

test('selecting a provider with a primary practice fills practice_id on create and document upload', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = trackerPractice('PRF');
    $provider = trackerProvider($practice);

    Livewire::actingAs($admin, 'admin')
        ->test(CredentialCreatePage::class)
        ->set('formData.provider_id', $provider->id)
        ->assertSet('formData.practice_id', $practice->id);

    Livewire::actingAs($admin, 'admin')
        ->test(DocumentListPage::class)
        ->set('formData.provider_id', $provider->id)
        ->assertSet('formData.practice_id', $practice->id);
});

test('tracker and details case numbers link to the credential show page', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = trackerPractice('CAS');
    $provider = trackerProvider($practice);
    $case = CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => Payer::create(['name' => 'Case Link Payer', 'is_active' => true])->id,
        'status_id' => Status::where('name', 'At Payer')->firstOrFail()->id,
        'state' => 'TX',
    ]);

    $showUrl = route('admin.credentials.show', $case);

    Livewire::actingAs($admin, 'admin')
        ->test(CredentialListPage::class)
        ->assertSeeHtml($showUrl)
        ->assertSeeHtml('wire:click="setFilterCategory(\'active\')"')
        ->assertSeeHtml('text-decoration-underline');

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderDetailsPage::class, ['provider' => $provider])
        ->assertSeeHtml($showUrl);

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeDetailsPage::class, ['practice' => $practice->id])
        ->set('activeTab', 'credentialing')
        ->assertSeeHtml($showUrl);
});

test('portal logins can be created edited and deleted on a provider and a practice', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = trackerPractice('LOG');
    $provider = trackerProvider($practice);

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderDetailsPage::class, ['provider' => $provider])
        ->assertSeeLivewire(PortalLoginsSection::class)
        ->assertSee('Provider logins');

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderEditPage::class, ['provider' => $provider->id])
        ->assertSeeLivewire(PortalLoginsSection::class);

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeDetailsPage::class, ['practice' => $practice->id])
        ->assertSeeLivewire(PortalLoginsSection::class)
        ->assertSee('Practice logins');

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeEditPage::class, ['practice' => $practice->id])
        ->assertSeeLivewire(PortalLoginsSection::class);

    $component = Livewire::actingAs($admin, 'admin')
        ->test(PortalLoginsSection::class, ['ownerType' => 'provider', 'ownerId' => $provider->id])
        ->call('openCreateModal')
        ->set('formData.portal_name', 'CAQH')
        ->set('formData.username', 'caqh-user')
        ->set('formData.password', 'VaultSecret99!')
        ->set('formData.mfa_email', 'mfa@example.com')
        ->set('formData.mfa_phone', '555-0100')
        ->set('formData.security_notes', 'Mother maiden name on file.')
        ->set('formData.account_details', 'Org ID 42')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('CAQH')
        ->assertSee('caqh-user')
        ->assertDontSee('VaultSecret99!');

    $login = $provider->portalLogins()->firstOrFail();
    expect($login->getRawOriginal('password'))->not->toBe('VaultSecret99!')
        ->and($login->makeVisible('password')->password)->toBe('VaultSecret99!');

    $component
        ->call('openEditModal', $login->id)
        ->assertSet('formData.password', 'VaultSecret99!')
        ->assertSet('showPassword', true)
        ->assertSeeHtml('value="VaultSecret99!"')
        ->set('formData.username', 'caqh-user-2')
        ->set('formData.password', 'NewVaultSecret!')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('caqh-user-2');

    expect($login->fresh()->makeVisible('password')->password)->toBe('NewVaultSecret!');

    $component->call('delete', $login->id);
    expect($provider->portalLogins()->count())->toBe(0);

    Livewire::actingAs($admin, 'admin')
        ->test(PortalLoginsSection::class, ['ownerType' => 'practice', 'ownerId' => $practice->id])
        ->call('openCreateModal')
        ->set('formData.portal_name', 'Availity')
        ->set('formData.password', 'PracticePass1!')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Availity')
        ->assertDontSee('PracticePass1!');

    $practiceLogin = $practice->portalLogins()->firstOrFail();
    expect($practiceLogin->makeVisible('password')->password)->toBe('PracticePass1!');
});

test('tracker KPI cards follow practice and provider filters', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practiceA = trackerPractice('KPA');
    $practiceB = trackerPractice('KPB');
    $providerA = trackerProvider($practiceA);
    $providerB = trackerProvider($practiceB);

    $payer = fn (string $name) => Payer::create(['name' => $name, 'is_active' => true])->id;
    $providerStatus = Status::where('name', 'Documents Requested from Provider')->firstOrFail()->id;
    $payerStatus = Status::where('name', 'At Payer')->firstOrFail()->id;
    $openStatus = Status::where('name', 'Not Started')->firstOrFail()->id;
    $closedStatus = Status::where('name', 'Approved')->firstOrFail()->id;

    CredentialingCase::create([
        'practice_id' => $practiceA->id,
        'provider_id' => $providerA->id,
        'payer_id' => $payer('KPI Payer A1'),
        'status_id' => $providerStatus,
        'state' => 'TX',
    ]);
    CredentialingCase::create([
        'practice_id' => $practiceA->id,
        'provider_id' => $providerA->id,
        'payer_id' => $payer('KPI Payer A2'),
        'status_id' => $payerStatus,
        'state' => 'TX',
        'next_follow_up_date' => now()->subDays(3)->toDateString(),
    ]);
    CredentialingCase::create([
        'practice_id' => $practiceB->id,
        'provider_id' => $providerB->id,
        'payer_id' => $payer('KPI Payer B1'),
        'status_id' => $openStatus,
        'state' => 'TX',
    ]);
    CredentialingCase::create([
        'practice_id' => $practiceB->id,
        'provider_id' => $providerB->id,
        'payer_id' => $payer('KPI Payer B2'),
        'status_id' => $closedStatus,
        'state' => 'TX',
    ]);

    $unfiltered = Livewire::actingAs($admin, 'admin')
        ->test(CredentialListPage::class)
        ->viewData('stats');

    expect($unfiltered['total_active'])->toBe(3)
        ->and($unfiltered['pending_provider'])->toBe(1)
        ->and($unfiltered['pending_payer'])->toBe(1)
        ->and($unfiltered['overdue'])->toBe(1);

    Livewire::actingAs($admin, 'admin')
        ->test(CredentialListPage::class)
        ->set('filterPracticeId', (string) $practiceA->id)
        ->assertViewHas('stats', fn (array $stats) => $stats['total_active'] === 2
            && $stats['pending_provider'] === 1
            && $stats['pending_payer'] === 1
            && $stats['overdue'] === 1);

    Livewire::actingAs($admin, 'admin')
        ->test(CredentialListPage::class)
        ->set('filterPracticeId', (string) $practiceB->id)
        ->assertViewHas('stats', fn (array $stats) => $stats['total_active'] === 1
            && $stats['pending_provider'] === 0
            && $stats['pending_payer'] === 0
            && $stats['overdue'] === 0);

    Livewire::actingAs($admin, 'admin')
        ->test(CredentialListPage::class)
        ->set('filterProviderId', (string) $providerA->id)
        ->assertViewHas('stats', fn (array $stats) => $stats['total_active'] === 2
            && $stats['pending_provider'] === 1
            && $stats['overdue'] === 1);

    Livewire::actingAs($admin, 'admin')
        ->test(CredentialListPage::class)
        ->set('filterCategory', 'provider')
        ->assertViewHas('stats', fn (array $stats) => $stats['total_active'] === 3
            && $stats['pending_provider'] === 1);
});
