<?php

use App\Enums\ProviderCredentialType;
use App\Livewire\Admin\Credential\CredentialListPage;
use App\Livewire\Admin\Documents\DocumentListPage;
use App\Livewire\Admin\Documents\EntityDocumentsSection;
use App\Livewire\Admin\Portal\PortalLoginsSection;
use App\Livewire\Admin\Provider\ProviderCreatePage;
use App\Livewire\Admin\Provider\ProviderEditPage;
use App\Models\Admin;
use App\Models\CredentialingCase;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Location;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\ProviderCredential;
use App\Models\ProviderDetails;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(AdminPermissionSeeder::class);
    $this->seed(AdminSeeder::class);
    $this->seed(MasterDataSeeder::class);
    $this->seed(RoleSeeder::class);
    Storage::fake('public');
});

function updateDocPractice(string $code = 'NUP'): Practice
{
    $practice = Practice::create([
        'legal_name' => 'Northside Update Practice',
        'client_code' => $code,
        'email' => strtolower($code).'@update.example',
        'status' => 'active',
        'user_id' => User::create([
            'name' => $code.' User',
            'email' => strtolower($code).'-user-'.uniqid().'@example.com',
            'password' => 'password',
        ])->id,
    ]);

    foreach ([
        'primary' => ['100 Primary Way', 'Austin', '78701'],
        'alternative' => ['200 Alt Ave', 'Dallas', '75201'],
        'mailing' => ['300 Mail Rd', 'Houston', '77001'],
        'billing' => ['400 Bill Blvd', 'San Antonio', '78201'],
    ] as $type => [$street, $city, $zip]) {
        $practice->addresses()->create([
            'type' => $type,
            'address1' => $street,
            'city' => $city,
            'state' => 'TX',
            'zip_code' => $zip,
            'country' => 'United States',
            'status' => 'active',
        ]);
    }

    Location::create([
        'practice_id' => $practice->id,
        'name' => 'West Facility',
        'address1' => '90 West Blvd',
        'city' => 'El Paso',
        'state' => 'TX',
        'zip_code' => '79901',
        'country' => 'United States',
        'status' => 'active',
        'is_primary' => true,
    ]);

    Location::create([
        'practice_id' => $practice->id,
        'name' => 'East Facility',
        'address1' => '91 East Blvd',
        'city' => 'Plano',
        'state' => 'TX',
        'zip_code' => '75024',
        'country' => 'United States',
        'status' => 'active',
        'is_primary' => false,
    ]);

    return $practice->fresh(['addresses', 'locations']);
}

function updateDocProvider(Practice $practice): ProviderDetails
{
    $provider = ProviderDetails::create([
        'user_id' => User::create([
            'name' => 'Dr. Update',
            'email' => 'dr-update-'.uniqid().'@example.com',
            'password' => 'password',
        ])->id,
        'npi' => (string) random_int(1000000000, 1999999999),
        'status' => 'active',
        'practice' => $practice->legal_name,
        'address' => '1 Test St',
        'city' => 'Austin',
        'state' => 'TX',
        'zip' => '78701',
    ]);

    $provider->practices()->syncWithoutDetaching([$practice->id => ['primary_flag' => true]]);

    return $provider;
}

test('provider practice address dropdown includes every practice address type and facility location', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = updateDocPractice('ADR');

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderCreatePage::class)
        ->set('practiceId', (string) $practice->id)
        ->assertSee('Primary Location Address')
        ->assertSee('Alternative Address (Location)')
        ->assertSee('Mailing Address')
        ->assertSee('Billing Address')
        ->assertSee('Facility Location (Primary)')
        ->assertSee('West Facility')
        ->assertSee('East Facility')
        ->assertSee('100 Primary Way')
        ->assertSee('300 Mail Rd')
        ->assertSee('400 Bill Blvd');

    $mailing = $practice->addresses->firstWhere('type', 'mailing');
    $east = $practice->locations->firstWhere('name', 'East Facility');

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderCreatePage::class)
        ->set('practiceId', (string) $practice->id)
        ->set('addressKey', 'address:'.$mailing->id)
        ->assertSet('formData.address', '300 Mail Rd')
        ->assertSet('formData.zip', '77001')
        ->set('addressKey', 'location:'.$east->id)
        ->assertSet('formData.address', '91 East Blvd')
        ->assertSet('formData.city', 'Plano');
});

test('edit provider omits removed license and credential rows and keeps CAQH ID in Professional Details', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = updateDocPractice('CRD');
    $provider = updateDocProvider($practice);

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderEditPage::class, ['provider' => $provider->id])
        ->assertDontSee('Licenses & other credentials')
        ->assertDontSee('State Licenses & Registrations')
        ->assertSee('CAQH ID')
        ->set('formData.caqh_id', '12345610')
        ->call('save')
        ->assertHasNoErrors();

    expect($provider->fresh()->caqh_id)->toBe('12345610');
});

test('practice and provider documents download through an authenticated route', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = updateDocPractice('DLP');
    $provider = updateDocProvider($practice);
    $type = DocumentType::where('name', 'W-9')->firstOrFail();
    $file = UploadedFile::fake()->create('download-me.pdf', 40, 'application/pdf');

    $practiceDoc = Document::createWithFile([
        'title' => 'Practice Download Doc',
        'document_type_id' => $type->id,
        'practice_id' => $practice->id,
    ], $file, $admin->id);

    $providerDoc = Document::createWithFile([
        'title' => 'Provider Download Doc',
        'document_type_id' => $type->id,
        'provider_id' => $provider->id,
    ], UploadedFile::fake()->create('provider-download.pdf', 40, 'application/pdf'), $admin->id);

    Livewire::actingAs($admin, 'admin')
        ->test(EntityDocumentsSection::class, ['practiceId' => $practice->id])
        ->assertSeeHtml(route('admin.documents.download', $practiceDoc, false));

    Livewire::actingAs($admin, 'admin')
        ->test(EntityDocumentsSection::class, ['providerId' => $provider->id])
        ->assertSeeHtml(route('admin.documents.download', $providerDoc, false));

    $this->actingAs($admin, 'admin')
        ->get(route('admin.documents.download', $practiceDoc))
        ->assertOk();

    $this->actingAs($admin, 'admin')
        ->get(route('admin.documents.download', $providerDoc))
        ->assertOk();
});

test('document repository can filter by payer and search client code', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = updateDocPractice('SRC');
    $provider = updateDocProvider($practice);
    $payer = Payer::create(['name' => 'Searchable Payer Plan', 'is_active' => true]);
    $type = DocumentType::where('name', 'W-9')->firstOrFail();

    Document::createWithFile([
        'title' => 'Client Code Hit',
        'document_type_id' => $type->id,
        'practice_id' => $practice->id,
    ], UploadedFile::fake()->create('code.pdf', 20, 'application/pdf'), $admin->id);

    $case = CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => Status::where('name', 'At Payer')->firstOrFail()->id,
        'state' => 'TX',
    ]);

    Document::createWithFile([
        'title' => 'Payer Filter Hit',
        'document_type_id' => $type->id,
        'credentialing_case_id' => $case->id,
        'payer_id' => $payer->id,
    ], UploadedFile::fake()->create('payer.pdf', 20, 'application/pdf'), $admin->id);

    Livewire::actingAs($admin, 'admin')
        ->test(DocumentListPage::class)
        ->assertSee('All Payers')
        ->assertSee('All Practices / client codes')
        ->set('search', 'SRC')
        ->assertSee('Client Code Hit')
        ->set('search', 'Northside Update Practice')
        ->assertSee('Client Code Hit')
        ->set('search', '')
        ->set('filterPayer', (string) $payer->id)
        ->assertSee('Payer Filter Hit')
        ->assertDontSee('Client Code Hit');
});

test('active applications kpi filters the tracker to open cases', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = updateDocPractice('KPI');
    $provider = updateDocProvider($practice);
    $payerId = Payer::create(['name' => 'KPI Filter Payer', 'is_active' => true])->id;
    $openStatus = Status::where('name', 'Not Started')->firstOrFail()->id;
    $closedStatus = Status::where('name', 'Approved')->firstOrFail()->id;

    $open = CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payerId,
        'status_id' => $openStatus,
        'state' => 'TX',
    ]);
    $closed = CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => Payer::create(['name' => 'KPI Closed Payer', 'is_active' => true])->id,
        'status_id' => $closedStatus,
        'state' => 'TX',
    ]);

    Livewire::actingAs($admin, 'admin')
        ->test(CredentialListPage::class)
        ->assertSee($open->case_number)
        ->assertSee($closed->case_number)
        ->call('setFilterCategory', 'active')
        ->assertSet('filterCategory', 'active')
        ->assertSee($open->case_number)
        ->assertDontSee($closed->case_number);
});

test('portal login edit shows the stored password instead of a blank field', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = updateDocPractice('PWD');
    $provider = updateDocProvider($practice);

    $login = $provider->portalLogins()->create([
        'portal_name' => 'CAQH',
        'username' => 'visible-user',
        'password' => 'VisiblePass99!',
    ]);

    Livewire::actingAs($admin, 'admin')
        ->test(PortalLoginsSection::class, ['ownerType' => 'provider', 'ownerId' => $provider->id])
        ->call('openEditModal', $login->id)
        ->assertSet('showPassword', true)
        ->assertSet('formData.password', 'VisiblePass99!')
        ->assertSeeHtml('value="VisiblePass99!"')
        ->assertSeeHtml('type="text"')
        ->call('togglePasswordVisibility')
        ->assertSet('showPassword', false);
});
