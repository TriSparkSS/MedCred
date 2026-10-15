<?php

use App\Enums\ProviderStatus;
use App\Livewire\Admin\Provider\ProviderCreatePage;
use App\Livewire\Admin\Provider\ProviderEditPage;
use App\Livewire\Admin\Provider\ProviderListPage;
use App\Models\Admin;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(AdminPermissionSeeder::class);
    $this->seed(AdminSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(SpecialtySeeder::class);
});

function statusTestPractice(): Practice
{
    return Practice::create([
        'legal_name' => 'Status Clinic',
        'client_code' => 'STC',
        'email' => 'status-clinic@example.com',
        'status' => 'active',
        'user_id' => User::create([
            'name' => 'Status Clinic User',
            'email' => 'status-clinic-user@example.com',
            'password' => 'password',
        ])->id,
    ]);
}

test('specialty dropdown is ordered and includes the seeded full list', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();

    $component = Livewire::actingAs($admin, 'admin')
        ->test(ProviderCreatePage::class);

    $names = collect($component->get('specialties'))->pluck('name')->values();
    $ordered = Specialty::orderBy('name')->pluck('name')->values();

    expect($names->count())->toBeGreaterThan(50)
        ->and($names->all())->toBe($ordered->all())
        ->and($names)->toContain('Family Medicine Physician')
        ->and($names)->toContain('Internal Medicine Physician')
        ->and($names->first())->toBe('Acupuncturist');

    $component->assertSee('Family Medicine Physician')
        ->assertSee('Search specialties...');
});

test('create and edit pages show provider status labels and not the old options', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = statusTestPractice();

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderCreatePage::class)
        ->assertSee('Provider Status')
        ->assertSee('Active')
        ->assertSee('Inactive')
        ->assertSee('On Hold')
        ->assertDontSee('Pending')
        ->assertDontSee('Approved')
        ->assertDontSee('Rejected');

    $provider = ProviderDetails::create([
        'user_id' => User::create([
            'name' => 'Dr. Status Edit',
            'email' => 'status-edit@example.com',
            'password' => 'password',
        ])->id,
        'npi' => '1555555555',
        'status' => ProviderStatus::ACTIVE,
        'practice' => $practice->legal_name,
        'address' => '1 Main St',
        'city' => 'Austin',
        'state' => 'TX',
        'zip' => '78701',
    ]);

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderEditPage::class, ['provider' => $provider->id])
        ->assertSee('Provider Status')
        ->assertSee('On Hold')
        ->assertDontSee('Pending');

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderListPage::class)
        ->assertSee('On Hold')
        ->assertDontSeeHtml('>Pending</option>')
        ->assertDontSeeHtml('>Approved</option>');
});

test('creating a provider on hold without remarks fails and with remarks saves', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = statusTestPractice();
    $specialty = Specialty::where('code', '207Q00000X')->firstOrFail();

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderCreatePage::class)
        ->set('userData.name', 'Dr. Hold')
        ->set('userData.email', 'hold@example.com')
        ->set('userData.password', 'secret123')
        ->set('formData.npi', '1666666666')
        ->set('formData.specialty_id', $specialty->id)
        ->set('practiceId', (string) $practice->id)
        ->set('formData.practice', 'Status Clinic')
        ->set('formData.address', '1 Main St')
        ->set('formData.city', 'Austin')
        ->set('formData.state', 'TX')
        ->set('formData.zip', '78701')
        ->set('formData.status', ProviderStatus::ON_HOLD->value)
        ->call('save')
        ->assertHasErrors(['formData.status_remarks']);

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderCreatePage::class)
        ->set('userData.name', 'Dr. Hold')
        ->set('userData.email', 'hold@example.com')
        ->set('userData.password', 'secret123')
        ->set('formData.npi', '1666666666')
        ->set('formData.specialty_id', $specialty->id)
        ->set('practiceId', (string) $practice->id)
        ->set('formData.practice', 'Status Clinic')
        ->set('formData.address', '1 Main St')
        ->set('formData.city', 'Austin')
        ->set('formData.state', 'TX')
        ->set('formData.zip', '78701')
        ->set('formData.status', ProviderStatus::ON_HOLD->value)
        ->set('formData.status_remarks', 'Awaiting CAQH attestation')
        ->call('save')
        ->assertHasNoErrors();

    $provider = ProviderDetails::where('npi', '1666666666')->firstOrFail();

    expect($provider->status)->toBe(ProviderStatus::ON_HOLD)
        ->and($provider->status_remarks)->toBe('Awaiting CAQH attestation')
        ->and($provider->specialty_id)->toBe($specialty->id);
});

test('editing a provider to on hold requires remarks', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = statusTestPractice();

    $provider = ProviderDetails::create([
        'user_id' => User::create([
            'name' => 'Dr. Existing Status',
            'email' => 'existing-status@example.com',
            'password' => 'password',
        ])->id,
        'npi' => '1777777777',
        'status' => ProviderStatus::ACTIVE,
        'practice' => $practice->legal_name,
        'address' => '2 Main St',
        'city' => 'Dallas',
        'state' => 'TX',
        'zip' => '75201',
    ]);

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderEditPage::class, ['provider' => $provider->id])
        ->set('formData.status', ProviderStatus::ON_HOLD->value)
        ->set('formData.status_remarks', '')
        ->call('save')
        ->assertHasErrors(['formData.status_remarks']);

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderEditPage::class, ['provider' => $provider->id])
        ->set('formData.status', ProviderStatus::ON_HOLD->value)
        ->set('formData.status_remarks', 'License verification in progress')
        ->call('save')
        ->assertHasNoErrors();

    $provider->refresh();

    expect($provider->status)->toBe(ProviderStatus::ON_HOLD)
        ->and($provider->status_remarks)->toBe('License verification in progress');
});

test('deleting a specialty sets provider specialty_id to null without cascade deleting provider', function () {
    $practice = statusTestPractice();
    $specialty = Specialty::create([
        'name' => 'Temporary Specialty',
        'code' => '999999999X',
    ]);

    $provider = ProviderDetails::create([
        'user_id' => User::create([
            'name' => 'Dr. Keep Provider',
            'email' => 'keep-provider@example.com',
            'password' => 'password',
        ])->id,
        'specialty_id' => $specialty->id,
        'npi' => '1888888888',
        'status' => ProviderStatus::ACTIVE,
        'practice' => $practice->legal_name,
        'address' => '3 Main St',
        'city' => 'Houston',
        'state' => 'TX',
        'zip' => '77001',
    ]);

    $specialty->delete();

    $provider->refresh();

    expect($provider->exists)->toBeTrue()
        ->and($provider->specialty_id)->toBeNull();
});

test('user providers and practices scopes properly parenthesize orWhereHas when chained with where', function () {
    $providerRoleUser = User::create([
        'name' => 'Role Provider Alpha',
        'email' => 'role-provider-alpha@example.com',
        'password' => 'password',
    ]);
    $providerRoleUser->assignRole('provider');

    $matchedProviderUser = User::create([
        'name' => 'Matched Provider Beta',
        'email' => 'matched-provider-beta@example.com',
        'password' => 'password',
    ]);
    $matchedProviderUser->assignRole('provider');

    $practiceRoleUser = User::create([
        'name' => 'Role Practice Alpha',
        'email' => 'role-practice-alpha@example.com',
        'password' => 'password',
    ]);
    $practiceRoleUser->assignRole('practice');

    $providersResult = User::where('email', 'matched-provider-beta@example.com')
        ->providers()
        ->pluck('email')
        ->all();

    expect($providersResult)->toBe(['matched-provider-beta@example.com']);

    $practicesResult = User::where('email', 'nonexistent@example.com')
        ->practices()
        ->count();

    expect($practicesResult)->toBe(0);
});

