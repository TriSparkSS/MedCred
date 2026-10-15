<?php

use App\Enums\ProviderCredentialType;
use App\Livewire\Admin\Documents\DocumentListPage;
use App\Livewire\Admin\Documents\EntityDocumentsSection;
use App\Livewire\Admin\Provider\ProviderCredentialsSection;
use App\Livewire\Admin\Provider\ProviderDetailsPage;
use App\Livewire\Admin\Provider\ProviderEditPage;
use App\Livewire\Admin\Provider\ProviderLocationsSection;
use App\Models\Admin;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Location;
use App\Models\Practice;
use App\Models\ProviderCredential;
use App\Models\ProviderDetails;
use App\Models\ProviderPracticeLocation;
use App\Models\User;
use App\Services\ProviderCredentialService;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(AdminPermissionSeeder::class);
    $this->seed(AdminSeeder::class);
    $this->seed(MasterDataSeeder::class);
});

function locationsTestPractice(): Practice
{
    $practice = Practice::create([
        'legal_name' => 'Multi Site Practice',
        'client_code' => 'MSP',
        'email' => 'msp@example.com',
        'status' => 'active',
        'user_id' => User::create([
            'name' => 'MSP User',
            'email' => 'msp-user@example.com',
            'password' => 'password',
        ])->id,
    ]);

    Location::create([
        'practice_id' => $practice->id,
        'name' => 'Main Clinic',
        'address1' => '100 Main St',
        'city' => 'Austin',
        'state' => 'TX',
        'zip_code' => '78701',
        'country' => 'United States',
        'status' => 'active',
        'is_primary' => true,
    ]);

    Location::create([
        'practice_id' => $practice->id,
        'name' => 'North Clinic',
        'address1' => '200 North Ave',
        'city' => 'Dallas',
        'state' => 'TX',
        'zip_code' => '75201',
        'country' => 'United States',
        'status' => 'active',
        'is_primary' => false,
    ]);

    return $practice->fresh('locations');
}

function locationsTestProvider(): ProviderDetails
{
    return ProviderDetails::create([
        'user_id' => User::create([
            'name' => 'Dr. Multi State',
            'email' => 'dr-multi@example.com',
            'password' => 'password',
        ])->id,
        'npi' => '1234567890',
        'status' => 'active',
        'practice' => 'Placeholder',
        'address' => '1 Test St',
        'city' => 'Austin',
        'state' => 'TX',
        'zip' => '78701',
    ]);
}

test('add location modal fills the provider primary practice', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = locationsTestPractice();
    $provider = locationsTestProvider();
    $provider->practices()->syncWithoutDetaching([$practice->id => ['primary_flag' => true]]);

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderLocationsSection::class, ['providerId' => $provider->id])
        ->call('openCreateModal')
        ->assertSet('formData.practice_id', (string) $practice->id)
        ->assertSet('showModal', true);
});

test('provider can be linked to all practice locations and only one is primary', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = locationsTestPractice();
    $provider = locationsTestProvider();
    $main = $practice->locations->firstWhere('name', 'Main Clinic');
    $north = $practice->locations->firstWhere('name', 'North Clinic');

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderLocationsSection::class, ['providerId' => $provider->id])
        ->call('openCreateModal')
        ->set('formData.practice_id', $practice->id)
        ->set('formData.location_id', $main->id)
        ->set('formData.is_primary', true)
        ->call('save')
        ->assertHasNoErrors();

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderLocationsSection::class, ['providerId' => $provider->id])
        ->call('openCreateModal')
        ->set('formData.practice_id', $practice->id)
        ->set('formData.location_id', $north->id)
        ->set('formData.is_primary', true)
        ->call('save')
        ->assertHasNoErrors();

    $links = ProviderPracticeLocation::where('provider_id', $provider->id)->orderBy('id')->get();

    expect($links)->toHaveCount(2);
    expect($links->firstWhere('location_id', $north->id)->is_primary)->toBeTrue();
    expect($links->firstWhere('location_id', $main->id)->is_primary)->toBeFalse();

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderLocationsSection::class, ['providerId' => $provider->id])
        ->assertSee('Main Clinic')
        ->assertSee('North Clinic')
        ->assertSee('100 Main St')
        ->assertSee('200 North Ave');
});

test('provider can store licenses in multiple states and rejects duplicates', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $provider = locationsTestProvider();
    $service = app(ProviderCredentialService::class);

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderCredentialsSection::class, ['providerId' => $provider->id])
        ->call('openCreateModal')
        ->set('formData.credential_type', ProviderCredentialType::License->value)
        ->set('formData.state', 'TX')
        ->set('formData.number', 'TX-111')
        ->set('formData.is_primary', true)
        ->call('save')
        ->assertHasNoErrors();

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderCredentialsSection::class, ['providerId' => $provider->id])
        ->call('openCreateModal')
        ->set('formData.credential_type', ProviderCredentialType::License->value)
        ->set('formData.state', 'CA')
        ->set('formData.number', 'CA-222')
        ->call('save')
        ->assertHasNoErrors();

    expect(ProviderCredential::where('provider_id', $provider->id)->count())->toBe(2);

    $provider->refresh();
    expect($provider->license_number)->toBe('TX-111')
        ->and($provider->license_state)->toBe('TX')
        ->and($provider->licensed_states)->toEqualCanonicalizing(['TX', 'CA']);

    expect(fn () => $service->save($provider, [
        'credential_type' => ProviderCredentialType::License->value,
        'state' => 'TX',
        'number' => 'TX-DUP',
    ]))->toThrow(ValidationException::class);
});

test('state license document upload requires a state', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $provider = locationsTestProvider();
    $type = DocumentType::where('name', 'State License')->firstOrFail();

    expect($type->is_state_specific)->toBeTrue();

    Storage::fake('public');
    $file = UploadedFile::fake()->create('license.pdf', 120, 'application/pdf');

    Livewire::actingAs($admin, 'admin')
        ->test(DocumentListPage::class)
        ->call('openUploadModal')
        ->set('formData.title', 'Texas Medical License')
        ->set('formData.document_type_id', $type->id)
        ->set('formData.provider_id', $provider->id)
        ->set('uploadFile', $file)
        ->call('saveDocument')
        ->assertHasErrors(['formData.state']);

    Livewire::actingAs($admin, 'admin')
        ->test(DocumentListPage::class)
        ->call('openUploadModal')
        ->set('formData.title', 'Texas Medical License')
        ->set('formData.document_type_id', $type->id)
        ->set('formData.provider_id', $provider->id)
        ->set('formData.state', 'TX')
        ->set('formData.sync_credential', true)
        ->set('uploadFile', $file)
        ->call('saveDocument')
        ->assertHasNoErrors();

    expect(Document::where('provider_id', $provider->id)->count())->toBe(1);
    expect(Document::first()->state)->toBe('TX');
    expect(ProviderCredential::where('provider_id', $provider->id)->where('state', 'TX')->exists())->toBeTrue();
});

test('provider credential and location section blades wrap in a single root with inline modals', function () {
    $credentials = file_get_contents(resource_path('views/livewire/admin/provider/partials/provider-credentials-section.blade.php'));
    $locations = file_get_contents(resource_path('views/livewire/admin/provider/partials/provider-locations-section.blade.php'));
    $documents = file_get_contents(resource_path('views/livewire/admin/documents/partials/entity-documents-section.blade.php'));

    expect(str_starts_with(trim($credentials), '<div>'))->toBeTrue()
        ->and(str_contains($credentials, '@teleport'))->toBeFalse()
        ->and(str_contains($credentials, 'position: fixed; inset: 0'))->toBeTrue()
        ->and(str_contains($credentials, 'credPrimary-{{ $providerId }}'))->toBeTrue()
        ->and(str_starts_with(trim($locations), '<div>'))->toBeTrue()
        ->and(str_contains($locations, '@teleport'))->toBeFalse()
        ->and(str_contains($locations, 'position: fixed; inset: 0'))->toBeTrue()
        ->and(str_contains($locations, 'pplPrimary-{{ $providerId }}'))->toBeTrue()
        ->and(str_contains($documents, "@php\n"))->toBeTrue()
        ->and(str_contains($documents, "@endphp\n\n<div>"))->toBeTrue()
        ->and(str_contains($documents, '@teleport'))->toBeFalse()
        ->and(str_contains($documents, 'position: fixed; inset: 0'))->toBeTrue();
});

test('provider credential and location rows can be edited after create', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = locationsTestPractice();
    $provider = locationsTestProvider();
    $main = $practice->locations->firstWhere('name', 'Main Clinic');

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderCredentialsSection::class, ['providerId' => $provider->id])
        ->call('openCreateModal')
        ->set('formData.credential_type', ProviderCredentialType::License->value)
        ->set('formData.state', 'TX')
        ->set('formData.number', 'TX-EDIT-1')
        ->call('save')
        ->assertHasNoErrors();

    $credential = ProviderCredential::where('provider_id', $provider->id)->firstOrFail();

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderCredentialsSection::class, ['providerId' => $provider->id])
        ->call('openEditModal', $credential->id)
        ->assertSet('showModal', true)
        ->assertSee('Edit Credential')
        ->assertSeeHtml('position: fixed; inset: 0')
        ->set('formData.number', 'TX-EDIT-2')
        ->call('save')
        ->assertHasNoErrors();

    expect($credential->fresh()->number)->toBe('TX-EDIT-2');

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderLocationsSection::class, ['providerId' => $provider->id])
        ->call('openCreateModal')
        ->set('formData.practice_id', $practice->id)
        ->set('formData.location_id', $main->id)
        ->set('formData.role', 'Attending')
        ->call('save')
        ->assertHasNoErrors();

    $link = ProviderPracticeLocation::where('provider_id', $provider->id)->firstOrFail();

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderLocationsSection::class, ['providerId' => $provider->id])
        ->call('openEditModal', $link->id)
        ->assertSet('showModal', true)
        ->assertSee('Edit Location Link')
        ->assertSeeHtml('position: fixed; inset: 0')
        ->set('formData.role', 'Covering')
        ->call('save')
        ->assertHasNoErrors();

    expect($link->fresh()->role)->toBe('Covering');
});

test('provider details page shows upload and location actions without a separate state license section', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $provider = locationsTestProvider();

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderDetailsPage::class, ['provider' => $provider])
        ->assertDontSeeLivewire(ProviderCredentialsSection::class)
        ->assertDontSee('State Licenses & Registrations')
        ->assertSeeLivewire(ProviderLocationsSection::class)
        ->assertSeeLivewire(EntityDocumentsSection::class)
        ->assertSee('Licenses & Credentials')
        ->assertSee('Edit credentials')
        ->assertSee('Add Location')
        ->assertSee('Upload Document');
});

test('provider edit page omits removed credential rows and opens document and location modals', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $provider = locationsTestProvider();

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderEditPage::class, ['provider' => $provider->id])
        ->assertSeeLivewire(EntityDocumentsSection::class)
        ->assertDontSeeLivewire(ProviderCredentialsSection::class)
        ->assertDontSee('State Licenses & Registrations')
        ->assertDontSee('Licenses & other credentials')
        ->assertSeeLivewire(ProviderLocationsSection::class)
        ->assertSee('Upload Document')
        ->assertSee('Add Location');

    Livewire::actingAs($admin, 'admin')
        ->test(EntityDocumentsSection::class, ['providerId' => $provider->id])
        ->call('openUploadModal')
        ->assertSet('showModal', true)
        ->assertSee('Upload Provider Document')
        ->assertSeeHtml('position: fixed; inset: 0');

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderLocationsSection::class, ['providerId' => $provider->id])
        ->call('openCreateModal')
        ->assertSet('showModal', true)
        ->assertSee('Link Location')
        ->assertSeeHtml('position: fixed; inset: 0');
});
