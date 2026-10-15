<?php

use App\Livewire\Admin\Credential\CredentialCreatePage;
use App\Livewire\Admin\Credential\CredentialDetailsPage;
use App\Livewire\Admin\Credential\CredentialEditPage;
use App\Livewire\Admin\Credential\CredentialListPage;
use App\Livewire\Admin\Documents\EntityDocumentsSection;
use App\Livewire\Admin\Portal\PortalLoginsSection;
use App\Livewire\Admin\Practices\PracticeEditPage;
use App\Livewire\Admin\Provider\ProviderCreatePage;
use App\Livewire\Admin\Provider\ProviderDetailsPage;
use App\Livewire\Admin\Provider\ProviderEditPage;
use App\Livewire\Admin\Provider\ProviderLocationsSection;
use App\Models\Admin;
use App\Models\CaseType;
use App\Models\CredentialingCase;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Location;
use App\Models\Payer;
use App\Models\PortalLogin;
use App\Models\Practice;
use App\Models\Priority;
use App\Models\ProviderDetails;
use App\Models\ProviderPracticeLocation;
use App\Models\Status;
use App\Models\User;
use App\Services\SpreadsheetImportService;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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

function credChangesSpreadsheetFile(string $filename, array $headers, array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray(array_merge([$headers], $rows));
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.$filename;
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, $filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

test('practice bulk import preloads alternative, mailing, billing, bank details, license fields, and up to 5 facility locations', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $service = app(SpreadsheetImportService::class);

    $headers = SpreadsheetImportService::PRACTICE_COLUMNS;
    $rowMap = [
        'legal_name' => 'Complete Import Practice LLC',
        'dba_name' => 'Complete Import Care',
        'client_code' => 'CIP',
        'email' => 'cip@example.com',
        'npi' => '1999888777',
        'tax_id' => '99-8877665',
        'phone' => '512-555-0100',
        'license_name' => 'Texas Clinic License',
        'license_number' => 'TX-FAC-500',
        'license_effective_date' => '2026-01-01',
        'license_expiry_date' => '2027-01-01',
        'status' => 'active',
        'address1' => '100 Primary Pkwy',
        'city' => 'Austin',
        'state' => 'TX',
        'zip_code' => '78701',
        'country' => 'United States',
        'alt_address1' => '200 Alternative Blvd',
        'alt_city' => 'Dallas',
        'alt_state' => 'TX',
        'alt_zip_code' => '75201',
        'mailing_address1' => '300 Mailing Ln',
        'mailing_city' => 'Houston',
        'mailing_state' => 'TX',
        'mailing_zip_code' => '77001',
        'billing_address1' => '400 Billing Ave',
        'billing_city' => 'San Antonio',
        'billing_state' => 'TX',
        'billing_zip_code' => '78201',
        'bank_name' => 'First Texas Bank',
        'bank_account' => '987654321',
        'bank_routing_number' => '111000025',
        'bank_phone' => '512-555-0199',
        'bank_address' => '1 Bank Plaza, Austin, TX',
        'facility_1_name' => 'Facility One',
        'facility_1_address1' => '101 Fac St',
        'facility_1_city' => 'Austin',
        'facility_1_state' => 'TX',
        'facility_1_zip_code' => '78702',
        'facility_1_npi_2' => '1222333444',
        'facility_2_name' => 'Facility Two',
        'facility_2_address1' => '202 Fac St',
        'facility_2_city' => 'Round Rock',
        'facility_2_state' => 'TX',
        'facility_2_zip_code' => '78664',
    ];

    $row = array_map(fn (string $col) => $rowMap[$col] ?? '', $headers);
    $file = credChangesSpreadsheetFile('practices-full.xlsx', $headers, [$row]);

    $batch = $service->importPractices($file, $admin->id);
    expect($batch->success_rows)->toBe(1)->and($batch->failed_rows)->toBe(0);

    $practice = Practice::with(['addresses', 'locations'])->where('client_code', 'CIP')->firstOrFail();

    expect($practice->license_name)->toBe('Texas Clinic License')
        ->and($practice->license_number)->toBe('TX-FAC-500')
        ->and($practice->license_effective_date?->toDateString())->toBe('2026-01-01')
        ->and($practice->license_expiry_date?->toDateString())->toBe('2027-01-01')
        ->and($practice->bank_name)->toBe('First Texas Bank')
        ->and($practice->bank_account)->toBe('987654321')
        ->and($practice->bank_routing_number)->toBe('111000025')
        ->and($practice->addresses->firstWhere('type', 'primary')?->address1)->toBe('100 Primary Pkwy')
        ->and($practice->addresses->firstWhere('type', 'alternative')?->address1)->toBe('200 Alternative Blvd')
        ->and($practice->addresses->firstWhere('type', 'mailing')?->address1)->toBe('300 Mailing Ln')
        ->and($practice->addresses->firstWhere('type', 'billing')?->address1)->toBe('400 Billing Ave')
        ->and($practice->locations)->toHaveCount(2)
        ->and((bool) $practice->locations->firstWhere('name', 'Facility One')?->is_primary)->toBeTrue()
        ->and($practice->locations->firstWhere('name', 'Facility Two')?->city)->toBe('Round Rock');
});

test('provider bulk import preloads dob, ssn, degree, provider_type, and home address and omits removed columns', function () {
    expect(SpreadsheetImportService::PROVIDER_COLUMNS)
        ->toContain('dob', 'ssn', 'degree', 'provider_type', 'home_address', 'home_city', 'home_state', 'home_zip')
        ->not->toContain('pecos_id', 'pecos_enrolled', 'board_certification', 'board_cert_expiry', 'license_number', 'license_state', 'license_expiry');

    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $service = app(SpreadsheetImportService::class);

    $headers = SpreadsheetImportService::PROVIDER_COLUMNS;
    $rowMap = [
        'name' => 'Dr. Home Details',
        'email' => 'dr-home@example.com',
        'phone' => '512-555-0123',
        'npi' => '1888777666',
        'specialty' => 'Internal Medicine',
        'practice' => 'Some Practice',
        'dob' => '1982-06-15',
        'ssn' => '123-45-6789',
        'degree' => 'MD',
        'provider_type' => 'Specialist',
        'address' => '100 Clinic Way',
        'city' => 'Austin',
        'state' => 'TX',
        'zip' => '78701',
        'home_address' => '450 Residence Dr',
        'home_city' => 'Cedar Park',
        'home_state' => 'TX',
        'home_zip' => '78613',
        'caqh_id' => '12345888',
        'status' => 'active',
    ];

    $row = array_map(fn (string $col) => $rowMap[$col] ?? '', $headers);
    $file = credChangesSpreadsheetFile('providers-full.xlsx', $headers, [$row]);

    $batch = $service->importProviders($file, $admin->id);
    expect($batch->success_rows)->toBe(1)->and($batch->failed_rows)->toBe(0);

    $provider = ProviderDetails::where('npi', '1888777666')->firstOrFail();
    expect($provider->dob?->toDateString())->toBe('1982-06-15')
        ->and($provider->ssn)->toBe('123-45-6789')
        ->and($provider->degree)->toBe('MD')
        ->and($provider->provider_type)->toBe('Specialist')
        ->and($provider->home_address)->toBe('450 Residence Dr')
        ->and($provider->home_city)->toBe('Cedar Park')
        ->and($provider->home_state)->toBe('TX')
        ->and($provider->home_zip)->toBe('78613')
        ->and($provider->caqh_id)->toBe('12345888');
});

test('provider create and edit save personal details, home address, and attach malpractice document directly', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = Practice::create([
        'legal_name' => 'Provider Create Practice',
        'client_code' => 'PCP1',
        'email' => 'pcp1@example.com',
        'status' => 'active',
        'user_id' => User::create(['name' => 'PCP1', 'email' => 'pcp1-u@example.com', 'password' => 'password'])->id,
    ]);
    $malpFile = UploadedFile::fake()->create('malpractice-policy.pdf', 64, 'application/pdf');

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderCreatePage::class)
        ->assertDontSee('Licenses & other credentials')
        ->assertDontSee('Board Certification')
        ->assertSee('Malpractice Insurance')
        ->assertSee('Attach Malpractice Document')
        ->set('practiceId', (string) $practice->id)
        ->set('userData.name', 'Dr. Jane Cred')
        ->set('userData.email', 'jane-cred@example.com')
        ->set('userData.password', 'secret123')
        ->set('formData.npi', '1555666777')
        ->set('formData.practice', $practice->legal_name)
        ->set('formData.address', '100 Clinic St')
        ->set('formData.city', 'Austin')
        ->set('formData.state', 'TX')
        ->set('formData.zip', '78701')
        ->set('formData.dob', '1985-04-20')
        ->set('formData.ssn', '999-11-2222')
        ->set('formData.degree', 'DO')
        ->set('formData.provider_type', 'PCP')
        ->set('formData.home_address', '742 Evergreen Terrace')
        ->set('formData.home_city', 'Austin')
        ->set('formData.home_state', 'TX')
        ->set('formData.home_zip', '78704')
        ->set('formData.caqh_id', '12345555')
        ->set('formData.malpractice_carrier', 'MedPro')
        ->set('formData.malpractice_policy_number', 'MP-12345')
        ->set('formData.malpractice_effective_date', '2026-01-01')
        ->set('formData.malpractice_expiry_date', '2027-01-01')
        ->set('malpracticeDocument', $malpFile)
        ->set('formData.status', 'active')
        ->call('save')
        ->assertHasNoErrors();

    $provider = ProviderDetails::where('npi', '1555666777')->firstOrFail();

    expect($provider->dob?->toDateString())->toBe('1985-04-20')
        ->and($provider->ssn)->toBe('999-11-2222')
        ->and($provider->degree)->toBe('DO')
        ->and($provider->provider_type)->toBe('PCP')
        ->and($provider->home_address)->toBe('742 Evergreen Terrace')
        ->and($provider->caqh_id)->toBe('12345555');

    $malpDoc = Document::where('provider_id', $provider->id)->firstOrFail();
    expect($malpDoc->documentType?->name)->toBe('Malpractice Insurance')
        ->and($malpDoc->document_number)->toBe('MP-12345');

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderDetailsPage::class, ['provider' => $provider])
        ->assertSee('04/20/1985')
        ->assertSee('999-11-2222')
        ->assertSee('DO')
        ->assertSee('PCP')
        ->assertSee('742 Evergreen Terrace');
});

test('portal logins section supports assigned_id up to 50 alphanumeric characters', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = Practice::create([
        'legal_name' => 'Portal ID Practice',
        'client_code' => 'PIP',
        'email' => 'pip@example.com',
        'status' => 'active',
        'user_id' => User::create(['name' => 'PIP', 'email' => 'pip-u@example.com', 'password' => 'password'])->id,
    ]);

    Livewire::actingAs($admin, 'admin')
        ->test(PortalLoginsSection::class, [
            'ownerType' => 'practice',
            'ownerId' => $practice->id,
        ])
        ->call('openCreateModal')
        ->set('formData.portal_name', 'Availity')
        ->set('formData.assigned_id', 'AVAIL-TX-2026-998877')
        ->set('formData.username', 'pip_admin')
        ->set('formData.password', 'SecretPass1!')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('AVAIL-TX-2026-998877');

    $login = $practice->portalLogins()->firstOrFail();
    expect($login->assigned_id)->toBe('AVAIL-TX-2026-998877');
});

test('provider locations section lists all practice addresses and facility locations and links selected practice address', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = Practice::create([
        'legal_name' => 'Multi Address Practice',
        'client_code' => 'MAP',
        'email' => 'map@example.com',
        'status' => 'active',
        'user_id' => User::create(['name' => 'MAP', 'email' => 'map-u@example.com', 'password' => 'password'])->id,
    ]);

    $billingAddress = $practice->addresses()->create([
        'type' => 'billing',
        'address1' => '800 Billing Tower',
        'city' => 'Houston',
        'state' => 'TX',
        'zip_code' => '77002',
        'country' => 'United States',
        'status' => 'active',
    ]);

    $provider = ProviderDetails::create([
        'user_id' => User::create(['name' => 'Dr. Loc', 'email' => 'dr-loc@example.com', 'password' => 'password'])->id,
        'npi' => '1777888999',
        'status' => 'active',
    ]);
    $provider->practices()->syncWithoutDetaching([$practice->id => ['primary_flag' => true]]);

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderLocationsSection::class, ['providerId' => $provider->id])
        ->call('openCreateModal')
        ->assertSee('Billing Address — 800 Billing Tower, Houston, TX')
        ->set('formData.practice_id', (string) $practice->id)
        ->set('formData.location_id', 'address:'.$billingAddress->id)
        ->set('formData.is_primary', true)
        ->call('save')
        ->assertHasNoErrors();

    $link = ProviderPracticeLocation::with('location')->where('provider_id', $provider->id)->firstOrFail();
    expect($link->location->address1)->toBe('800 Billing Tower')
        ->and($link->location->city)->toBe('Houston')
        ->and($link->is_primary)->toBeTrue();
});

test('new document types exist including state-specific CDS and correspondence types', function () {
    $cds = DocumentType::where('name', 'CDS')->first();
    expect($cds)->not->toBeNull()
        ->and($cds->is_state_specific)->toBeTrue();

    foreach ([
        'CP-575 / IRS',
        'Education Certificate / Degree',
        'Payer Correspondence',
        'Additional Info Request Letter',
        'Submission Proof',
    ] as $typeName) {
        expect(DocumentType::where('name', $typeName)->exists())->toBeTrue();
    }
});

test('credential create auto-derives state, uploads additional documents linked to practice and provider, and credential edit updates case', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = Practice::create([
        'legal_name' => 'Tracker Test Practice',
        'client_code' => 'TTP',
        'email' => 'ttp@example.com',
        'status' => 'active',
        'user_id' => User::create(['name' => 'TTP', 'email' => 'ttp-u@example.com', 'password' => 'password'])->id,
    ]);
    $location = Location::create([
        'practice_id' => $practice->id,
        'name' => 'Austin Main',
        'address1' => '100 Congress Ave',
        'city' => 'Austin',
        'state' => 'TX',
        'zip_code' => '78701',
        'country' => 'United States',
        'status' => 'active',
        'is_primary' => true,
    ]);
    $provider = ProviderDetails::create([
        'user_id' => User::create(['name' => 'Dr. Tracker', 'email' => 'dr-tracker@example.com', 'password' => 'password'])->id,
        'npi' => '1666555444',
        'state' => 'TX',
        'status' => 'active',
    ]);
    $provider->practices()->syncWithoutDetaching([$practice->id => ['primary_flag' => true]]);

    $payer = Payer::create(['name' => 'Aetna Texas', 'is_active' => true]);
    $caseType = CaseType::firstOrFail();
    $status = Status::where('name', 'Not Started')->first() ?? Status::firstOrFail();
    $priority = Priority::firstOrFail();
    $corrType = DocumentType::where('name', 'Payer Correspondence')->firstOrFail();
    $proofFile = UploadedFile::fake()->create('submission-proof.pdf', 50, 'application/pdf');

    Livewire::actingAs($admin, 'admin')
        ->test(CredentialCreatePage::class)
        ->set('formData.practice_id', $practice->id)
        ->set('formData.provider_id', $provider->id)
        ->set('formData.location_id', $location->id)
        ->set('formData.payer_id', $payer->id)
        ->set('formData.case_type_id', $caseType->id)
        ->set('formData.status_id', $status->id)
        ->set('formData.priority_id', $priority->id)
        ->set('additionalDocumentTypeId', $corrType->id)
        ->set('additionalDocuments', [$proofFile])
        ->set('linkAdditionalDocsToPractice', true)
        ->set('linkAdditionalDocsToProvider', true)
        ->call('save')
        ->assertHasNoErrors();

    $case = CredentialingCase::latest('id')->firstOrFail();
    expect($case->state)->toBe('TX');

    $doc = Document::where('credentialing_case_id', $case->id)->firstOrFail();
    expect($doc->document_type_id)->toBe($corrType->id)
        ->and($doc->practice_id)->toBe($practice->id)
        ->and($doc->provider_id)->toBe($provider->id);

    // Edit Application page updates case fields
    $newStatus = Status::where('id', '!=', $status->id)->firstOrFail();
    Livewire::actingAs($admin, 'admin')
        ->test(CredentialEditPage::class, ['case' => $case])
        ->assertSee($case->case_number)
        ->set('formData.status_id', $newStatus->id)
        ->set('formData.notes', 'Updated via CredentialEditPage')
        ->call('save')
        ->assertHasNoErrors();

    $case->refresh();
    expect($case->status_id)->toBe($newStatus->id)
        ->and($case->notes)->toBe('Updated via CredentialEditPage');

    // CredentialDetailsPage documents tab embeds EntityDocumentsSection for case
    Livewire::actingAs($admin, 'admin')
        ->test(CredentialDetailsPage::class, ['case' => $case, 'activeTab' => 'documents'])
        ->assertSee('Edit Application')
        ->assertSee('Additional Application Documents')
        ->assertSeeLivewire(EntityDocumentsSection::class);

    // CredentialListPage shows Edit button
    Livewire::actingAs($admin, 'admin')
        ->test(CredentialListPage::class)
        ->assertSeeHtml(route('admin.credentials.edit', $case));
});
