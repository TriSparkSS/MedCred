<?php

use App\Livewire\Admin\Provider\ProviderCreatePage;
use App\Livewire\Admin\Provider\ProviderDetailsPage;
use App\Livewire\Admin\Provider\ProviderEditPage;
use App\Models\Admin;
use App\Models\ProviderDetails;
use App\Models\User;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(AdminPermissionSeeder::class);
    $this->seed(AdminSeeder::class);
    $this->seed(RoleSeeder::class);
});

test('creating a provider stores malpractice coverage amounts and effective date', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderCreatePage::class)
        ->set('userData.name', 'Dr. Coverage')
        ->set('userData.email', 'coverage@example.com')
        ->set('userData.password', 'secret123')
        ->set('formData.npi', '1999999999')
        ->set('formData.practice', 'Coverage Clinic')
        ->set('formData.address', '1 Main St')
        ->set('formData.city', 'Austin')
        ->set('formData.state', 'TX')
        ->set('formData.zip', '78701')
        ->set('formData.status', 'pending')
        ->set('formData.pecos_enrolled', false)
        ->set('formData.malpractice_carrier', 'MedPro')
        ->set('formData.malpractice_policy_number', 'MP-100')
        ->set('formData.malpractice_coverage_each_occurrence', '1000000')
        ->set('formData.malpractice_coverage_aggregate', '3000000')
        ->set('formData.malpractice_effective_date', '2026-01-15')
        ->set('formData.malpractice_expiry', '2027-01-15')
        ->call('save')
        ->assertHasNoErrors();

    $provider = ProviderDetails::where('npi', '1999999999')->firstOrFail();

    expect((float) $provider->malpractice_coverage_each_occurrence)->toBe(1000000.0)
        ->and((float) $provider->malpractice_coverage_aggregate)->toBe(3000000.0)
        ->and($provider->malpractice_effective_date?->toDateString())->toBe('2026-01-15');
});

test('editing a provider persists malpractice coverage fields and they show on details', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $provider = ProviderDetails::create([
        'user_id' => User::create([
            'name' => 'Dr. Existing',
            'email' => 'existing-mal@example.com',
            'password' => 'password',
        ])->id,
        'npi' => '1888888888',
        'status' => 'approved',
        'practice' => 'Existing Clinic',
        'address' => '2 Main St',
        'city' => 'Dallas',
        'state' => 'TX',
        'zip' => '75201',
        'malpractice_carrier' => 'Old Carrier',
    ]);

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderEditPage::class, ['provider' => $provider->id])
        ->set('formData.malpractice_coverage_each_occurrence', '1000000')
        ->set('formData.malpractice_coverage_aggregate', '3000000')
        ->set('formData.malpractice_effective_date', '2026-03-01')
        ->set('formData.malpractice_expiry', '2027-03-01')
        ->call('save')
        ->assertHasNoErrors();

    $provider->refresh();

    expect((float) $provider->malpractice_coverage_each_occurrence)->toBe(1000000.0)
        ->and((float) $provider->malpractice_coverage_aggregate)->toBe(3000000.0)
        ->and($provider->malpractice_effective_date?->toDateString())->toBe('2026-03-01');

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderDetailsPage::class, ['provider' => $provider])
        ->set('activeTab', 'licenses')
        ->assertSee('$1,000,000')
        ->assertSee('$3,000,000')
        ->assertSee('Effective 03/01/2026');
});
