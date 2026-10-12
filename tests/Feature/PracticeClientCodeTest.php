<?php

use App\Livewire\Admin\Practices\PracticeCreatePage;
use App\Livewire\Admin\Practices\PracticeEditPage;
use App\Models\Admin;
use App\Models\CredentialingCase;
use App\Models\Practice;
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

function clientCodePractice(string $code, string $email = 'client-code-practice@example.com'): Practice
{
    return Practice::create([
        'legal_name' => 'Client Code Practice',
        'client_code' => $code,
        'email' => $email,
        'status' => 'active',
        'user_id' => User::create([
            'name' => 'Client Code User',
            'email' => str_replace('@', '-user@', $email),
            'password' => 'password',
        ])->id,
    ]);
}

test('practice stores numeric and mixed alphanumeric client codes', function () {
    $numeric = clientCodePractice('116', 'numeric-code@example.com');
    $mixed = clientCodePractice('RVA116', 'mixed-code@example.com');
    $letters = clientCodePractice('RVA', 'letters-code@example.com');

    expect($numeric->client_code)->toBe('116')
        ->and($mixed->client_code)->toBe('RVA116')
        ->and($letters->client_code)->toBe('RVA');
});

test('numeric client codes are used in generated case numbers', function () {
    $practice = clientCodePractice('116');

    $number = CredentialingCase::generateCaseNumber($practice->id);

    expect($number)->toStartWith('116-'.now()->format('Y').'-');
});

test('practice create accepts a numeric client code longer than three characters', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeCreatePage::class)
        ->set('formData.legal_name', 'Numeric Code Practice')
        ->set('formData.client_code', '1167')
        ->set('formData.email', 'numeric-create@example.com')
        ->set('formData.status', 'pending')
        ->set('userData.password', 'secret123')
        ->set('addressData.address1', '10 Main St')
        ->set('addressData.city', 'Austin')
        ->set('addressData.state', 'TX')
        ->set('addressData.zip_code', '78701')
        ->set('addressData.country', 'United States')
        ->call('save')
        ->assertHasNoErrors();

    expect(Practice::where('client_code', '1167')->exists())->toBeTrue();
});

test('practice edit accepts a numeric client code', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = clientCodePractice('RVA', 'edit-numeric@example.com');

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeEditPage::class, ['practice' => $practice->id])
        ->set('formData.client_code', '116')
        ->set('addressData.address1', '10 Main St')
        ->set('addressData.city', 'Austin')
        ->set('addressData.state', 'TX')
        ->set('addressData.zip_code', '78701')
        ->set('addressData.country', 'United States')
        ->call('save')
        ->assertHasNoErrors();

    expect($practice->fresh()->client_code)->toBe('116');
});

test('practice edit stores license name number and dates', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = clientCodePractice('LIC', 'license-dates@example.com');

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeEditPage::class, ['practice' => $practice->id])
        ->set('formData.license_name', 'State facility license')
        ->set('formData.license_number', 'TX-4455')
        ->set('formData.license_effective_date', '2026-02-01')
        ->set('formData.license_expiry_date', '2027-02-01')
        ->set('addressData.address1', '10 Main St')
        ->set('addressData.city', 'Austin')
        ->set('addressData.state', 'TX')
        ->set('addressData.zip_code', '78701')
        ->set('addressData.country', 'United States')
        ->call('save')
        ->assertHasNoErrors();

    $practice->refresh();

    expect($practice->license_name)->toBe('State facility license')
        ->and($practice->license_number)->toBe('TX-4455')
        ->and($practice->license_effective_date?->toDateString())->toBe('2026-02-01')
        ->and($practice->license_expiry_date?->toDateString())->toBe('2027-02-01');

    $document = $practice->documents()->first();
    expect($document)->not->toBeNull()
        ->and($document->title)->toBe('State facility license')
        ->and($document->document_number)->toBe('TX-4455')
        ->and($document->effective_date?->toDateString())->toBe('2026-02-01')
        ->and($document->expiry_date?->toDateString())->toBe('2027-02-01');
});
