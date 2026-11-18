<?php

use App\Livewire\Admin\DashboardPage;
use App\Livewire\Admin\Practices\PracticeCreatePage;
use App\Livewire\Admin\Provider\ProviderCreatePage;
use App\Models\Admin;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Location;
use App\Models\Practice;
use App\Models\PracticeContact;
use App\Models\ProviderDetails;
use App\Models\Task;
use App\Models\User;
use App\Services\AdminScopeService;
use App\Services\ProviderCredentialService;
use App\Services\SpreadsheetImportService;
use App\Services\ZipLookupService;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(AdminPermissionSeeder::class);
    $this->seed(AdminSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(MasterDataSeeder::class);
});

function buildCsvUpload(array $columns, array $rowValues, string $filename = 'import.csv'): UploadedFile
{
    $lines = [];
    $fh = fopen('php://temp', 'r+');
    fputcsv($fh, $columns);
    $row = [];
    foreach ($columns as $col) {
        $row[] = $rowValues[$col] ?? '';
    }
    fputcsv($fh, $row);
    rewind($fh);
    $csvContent = stream_get_contents($fh);
    fclose($fh);

    return UploadedFile::fake()->createWithContent($filename, (string) $csvContent);
}

test('item 1: Add Practice primary address lookupZip fills county, falls back to Census geocoder, and short-caches missing county', function () {
    Cache::flush();

    $fccShouldFailFor30301 = true;
    $censusShouldFailFor30301 = true;

    Http::fake(function ($request) use (&$fccShouldFailFor30301, &$censusShouldFailFor30301) {
        $url = $request->url();

        if (str_contains($url, 'api.zippopotam.us/us/10001')) {
            return Http::response([
                'places' => [[
                    'place name' => 'New York City',
                    'state abbreviation' => 'NY',
                    'latitude' => '40.7484',
                    'longitude' => '-73.9967',
                ]],
            ], 200);
        }

        if (str_contains($url, 'api.zippopotam.us/us/30301')) {
            return Http::response([
                'places' => [[
                    'place name' => 'Atlanta',
                    'state abbreviation' => 'GA',
                    'latitude' => '33.7490',
                    'longitude' => '-84.3880',
                ]],
            ], 200);
        }

        if (str_contains($url, 'geo.fcc.gov')) {
            if (str_contains($url, '40.7484')) {
                return Http::response([
                    'results' => [['county_name' => 'New York County']],
                ], 200);
            }

            return $fccShouldFailFor30301
                ? Http::response([], 503)
                : Http::response(['results' => [['county_name' => 'Fulton County']]], 200);
        }

        if (str_contains($url, 'geocoding.geo.census.gov')) {
            return $censusShouldFailFor30301
                ? Http::response([], 503)
                : Http::response(['result' => ['geographies' => ['Counties' => [['NAME' => 'Fulton County']]]]], 200);
        }

        return Http::response([], 404);
    });

    $admin = Admin::where('username', 'superadmin')->firstOrFail();

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeCreatePage::class)
        ->set('addressData.zip_code', '10001')
        ->call('lookupZip', 'addressData')
        ->assertSet('addressData.city', 'New York City')
        ->assertSet('addressData.state', 'NY')
        ->assertSet('addressData.county', 'New York County');

    $service = app(ZipLookupService::class);

    // 1) Both county APIs fail -> city/state returned, county is null
    $first = $service->lookup('30301');
    expect($first['city'])->toBe('Atlanta')
        ->and($first['county'])->toBeNull();

    // 2) After 11 minutes (> 10 min short TTL), Census recovers while FCC still fails -> Census supplies county
    $this->travel(11)->minutes();
    $censusShouldFailFor30301 = false;

    $second = $service->lookup('30301');
    expect($second['county'])->toBe('Fulton County');
});

test('items 2 & 3: users table has role and status columns, bulk import succeeds for practices and providers, and database errors are sanitized', function () {
    expect(Schema::hasColumn('users', 'role'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'status'))->toBeTrue();

    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $this->actingAs($admin, 'admin');

    $service = app(SpreadsheetImportService::class);

    $practiceFile = buildCsvUpload(SpreadsheetImportService::PRACTICE_COLUMNS, [
        'legal_name' => 'Imported Valley Clinic',
        'client_code' => 'IVC01',
        'email' => 'ivc01@example.com',
        'status' => 'active',
        'address1' => '200 Main St',
        'city' => 'Dallas',
        'state' => 'TX',
        'zip_code' => '75201',
        'country' => 'United States',
    ], 'practices.csv');

    $practiceBatch = $service->importPractices($practiceFile, $admin->id);
    expect($practiceBatch->success_rows)->toBe(1)
        ->and($practiceBatch->failed_rows)->toBe(0)
        ->and($practiceBatch->errors)->toBeNull()
        ->and(Practice::where('client_code', 'IVC01')->exists())->toBeTrue();

    $providerFile = buildCsvUpload(SpreadsheetImportService::PROVIDER_COLUMNS, [
        'name' => 'Dr. Imported Smith',
        'email' => 'imported.smith@example.com',
        'npi' => '1887766554',
        'practice' => 'Imported Valley Clinic',
        'address' => '200 Main St',
        'city' => 'Dallas',
        'state' => 'TX',
        'zip' => '75201',
        'status' => 'active',
    ], 'providers.csv');

    $providerBatch = $service->importProviders($providerFile, $admin->id);
    expect($providerBatch->success_rows)->toBe(1)
        ->and($providerBatch->failed_rows)->toBe(0)
        ->and($providerBatch->errors)->toBeNull()
        ->and(ProviderDetails::where('npi', '1887766554')->exists())->toBeTrue();

    // Verify raw SQL errors are sanitized via friendlyRowError()
    $subclassed = new class(app(AdminScopeService::class), app(ProviderCredentialService::class)) extends SpreadsheetImportService
    {
        public function exposeFriendlyError(Throwable $e): string
        {
            return $this->friendlyRowError($e);
        }
    };

    $queryException = new QueryException(
        'mysql',
        'insert into `users` (`name`, `role`) values (?, ?)',
        ['Alice', 'provider'],
        new Exception("Unknown column 'role' in 'field list'")
    );

    $friendly = $subclassed->exposeFriendlyError($queryException);
    expect($friendly)->toBe('Could not save this row because of a database error. Please contact support.')
        ->and($friendly)->not->toContain('insert into')
        ->and($friendly)->not->toContain('Unknown column');
});

test('item 4: Dashboard Assigned to me Open and Closed count badges use visible contrast styling', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();

    Task::create([
        'title' => 'Assigned Open Task',
        'status' => 'open',
        'priority' => 'normal',
        'assigned_admin_id' => $admin->id,
        'created_by_admin_id' => $admin->id,
        'due_date' => now()->addDays(3)->toDateString(),
    ]);

    Task::create([
        'title' => 'Assigned Completed Task',
        'status' => 'completed',
        'priority' => 'normal',
        'assigned_admin_id' => $admin->id,
        'created_by_admin_id' => $admin->id,
        'completed_at' => now(),
    ]);

    Livewire::actingAs($admin, 'admin')
        ->test(DashboardPage::class)
        ->assertSet('assignmentFilter', 'open')
        ->assertSeeHtml('class="badge bg-white text-primary ms-1">1</span>')
        ->call('setAssignmentFilter', 'closed')
        ->assertSet('assignmentFilter', 'closed')
        ->assertSeeHtml('class="badge bg-white text-primary ms-1">1</span>');
});

test('items 5 & 6: Add Practice saves Bank Documents, Additional Documents, Practice logins, Practice Contacts, and Facility Locations atomically', function () {
    Storage::fake('public');
    Cache::flush();

    Http::fake([
        'api.zippopotam.us/us/78701' => Http::response([
            'places' => [[
                'place name' => 'Austin',
                'state abbreviation' => 'TX',
                'latitude' => '30.2711',
                'longitude' => '-97.7437',
            ]],
        ], 200),
        'geo.fcc.gov/*' => Http::response([
            'results' => [['county_name' => 'Travis County']],
        ], 200),
    ]);

    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $w9Type = DocumentType::firstOrCreate(
        ['name' => 'W-9 Form'],
        ['is_active' => true, 'is_state_specific' => false]
    );

    $bankPdf = UploadedFile::fake()->create('voided-check.pdf', 32, 'application/pdf');
    $w9Pdf = UploadedFile::fake()->create('w9.pdf', 32, 'application/pdf');

    // First verify validation failure rolls back / prevents partial creation
    $initialPracticeCount = Practice::count();
    $initialUserCount = User::count();

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeCreatePage::class)
        ->assertSee('Bank Documents')
        ->assertSee('Practice logins')
        ->assertSee('Practice Contacts')
        ->assertSee('Facility Locations')
        ->set('formData.legal_name', 'Rollback Clinic')
        ->set('formData.client_code', 'RBC01')
        ->set('formData.email', 'rollback-clinic@example.com')
        ->set('formData.status', 'active')
        ->set('userData.password', 'Secret123!')
        ->set('addressData.address1', '100 First Ave')
        ->set('addressData.city', 'Austin')
        ->set('addressData.state', 'TX')
        ->set('addressData.zip_code', '78701')
        ->set('addressData.country', 'United States')
        ->call('addContactRow')
        ->set('contacts.0.name', 'Bad Email Contact')
        ->set('contacts.0.email', 'not-an-email')
        ->call('save')
        ->assertHasErrors(['contacts.0.email']);

    expect(Practice::count())->toBe($initialPracticeCount)
        ->and(User::count())->toBe($initialUserCount);

    // Now test full atomic creation including repeater ZIP lookup
    Livewire::actingAs($admin, 'admin')
        ->test(PracticeCreatePage::class)
        ->set('formData.legal_name', 'All-In-One Medical Group')
        ->set('formData.client_code', 'AIO01')
        ->set('formData.email', 'aio-group@example.com')
        ->set('formData.status', 'active')
        ->set('userData.password', 'Secret123!')
        ->set('addressData.address1', '500 Congress Ave')
        ->set('addressData.city', 'Austin')
        ->set('addressData.state', 'TX')
        ->set('addressData.zip_code', '78701')
        ->set('addressData.country', 'United States')
        // Bank Document
        ->call('addDocumentRow', 'bankDocuments')
        ->set('bankDocuments.0.title', 'Chase Voided Check')
        ->set('bankDocuments.0.document_number', 'CHK-9001')
        ->set('bankDocuments.0.file', $bankPdf)
        // Additional License / Document
        ->call('addDocumentRow', 'licenseDocuments')
        ->set('licenseDocuments.0.document_type_id', (string) $w9Type->id)
        ->set('licenseDocuments.0.title', '2026 Signed W-9')
        ->set('licenseDocuments.0.file', $w9Pdf)
        // Practice Portal Login
        ->call('addPortalLoginRow')
        ->set('portalLogins.0.portal_name', 'Availity')
        ->set('portalLogins.0.username', 'aio_availity')
        ->set('portalLogins.0.password', 'PortalSecret#99')
        ->set('portalLogins.0.portal_url', 'https://apps.availity.com')
        // Practice Contact
        ->call('addContactRow')
        ->set('contacts.0.name', 'Sarah Connor')
        ->set('contacts.0.title', 'Practice Manager')
        ->set('contacts.0.email', 'sarah@aio-group.example.com')
        ->set('contacts.0.is_primary', true)
        // Facility Location + repeater ZIP lookup
        ->call('addFacilityLocationRow')
        ->set('facilityLocations.0.name', 'Downtown Imaging Center')
        ->set('facilityLocations.0.address1', '600 Congress Ave')
        ->set('facilityLocations.0.zip_code', '78701')
        ->call('lookupZip', 'facilityLocations.0')
        ->assertSet('facilityLocations.0.city', 'Austin')
        ->assertSet('facilityLocations.0.state', 'TX')
        ->assertSet('facilityLocations.0.county', 'Travis County')
        ->call('save')
        ->assertHasNoErrors();

    $practice = Practice::where('client_code', 'AIO01')->firstOrFail();

    expect(PracticeContact::where('practice_id', $practice->id)->where('name', 'Sarah Connor')->exists())->toBeTrue()
        ->and(Location::where('practice_id', $practice->id)->where('name', 'Downtown Imaging Center')->where('county', 'Travis County')->exists())->toBeTrue();

    $portalLogin = $practice->portalLogins()->where('portal_name', 'Availity')->first();
    expect($portalLogin)->not->toBeNull()
        ->and($portalLogin->password)->toBe('PortalSecret#99')
        ->and(DB::table('portal_logins')->where('id', $portalLogin->id)->value('password'))->not->toBe('PortalSecret#99');

    $bankDocType = DocumentType::where('name', 'Bank Document')->firstOrFail();
    expect(Document::where('practice_id', $practice->id)->where('document_type_id', $bankDocType->id)->where('title', 'Chase Voided Check')->exists())->toBeTrue()
        ->and(Document::where('practice_id', $practice->id)->where('document_type_id', $w9Type->id)->where('title', '2026 Signed W-9')->exists())->toBeTrue();
});

test('item 7: Add Provider displays Facility Locations, License & Documents, and Provider logins and saves them atomically', function () {
    Storage::fake('public');

    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practiceUser = User::create([
        'name' => 'Provider Host Practice',
        'email' => 'host-practice@example.com',
        'password' => 'Secret123!',
    ]);
    $practice = Practice::create([
        'user_id' => $practiceUser->id,
        'legal_name' => 'Provider Host Practice',
        'client_code' => 'PHP01',
        'email' => 'host-practice@example.com',
        'status' => 'active',
    ]);

    $deaType = DocumentType::firstOrCreate(
        ['name' => 'DEA Certificate'],
        ['is_active' => true, 'is_state_specific' => false]
    );
    $deaPdf = UploadedFile::fake()->create('dea-cert.pdf', 24, 'application/pdf');

    $initialProviderCount = ProviderDetails::count();

    // Validation failure on pending provider portal login saves nothing
    Livewire::actingAs($admin, 'admin')
        ->test(ProviderCreatePage::class)
        ->set('userData.name', 'Dr. Rollback Test')
        ->set('userData.email', 'rollback.provider@example.com')
        ->set('userData.password', 'DoctorPass123!')
        ->set('formData.npi', '1554433229')
        ->set('formData.status', 'active')
        ->set('practiceId', (string) $practice->id)
        ->set('formData.address', '700 Medical Plaza')
        ->set('formData.city', 'Houston')
        ->set('formData.state', 'TX')
        ->set('formData.zip', '77002')
        ->call('addPortalLoginRow')
        ->set('portalLogins.0.portal_name', '') // missing required portal_name
        ->set('portalLogins.0.password', 'CaqhPass#2026')
        ->call('save')
        ->assertHasErrors(['portalLogins.0.portal_name']);

    expect(ProviderDetails::count())->toBe($initialProviderCount);

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderCreatePage::class)
        ->assertSee('Facility Locations')
        ->assertSee('License & Documents')
        ->assertSee('Provider logins')
        ->set('userData.name', 'Dr. Clara Oswald')
        ->set('userData.email', 'clara.oswald@example.com')
        ->set('userData.password', 'DoctorPass123!')
        ->set('formData.npi', '1554433221')
        ->set('formData.status', 'active')
        ->set('practiceId', (string) $practice->id)
        ->set('formData.address', '700 Medical Plaza')
        ->set('formData.city', 'Houston')
        ->set('formData.state', 'TX')
        ->set('formData.zip', '77002')
        // Add a new Facility Location via modal
        ->call('openLocationModal')
        ->set('locationForm.mode', 'new')
        ->set('locationForm.name', 'Houston Heart Center')
        ->set('locationForm.address1', '750 Medical Plaza')
        ->set('locationForm.city', 'Houston')
        ->set('locationForm.state', 'TX')
        ->set('locationForm.zip_code', '77002')
        ->set('locationForm.is_primary', true)
        ->call('addServiceLocation')
        // Add Provider Document (state-specific DEA Certificate)
        ->call('addDocumentRow', 'providerDocuments')
        ->set('providerDocuments.0.document_type_id', (string) $deaType->id)
        ->set('providerDocuments.0.title', 'Federal DEA Certificate')
        ->set('providerDocuments.0.document_number', 'BO1234567')
        ->set('providerDocuments.0.state', 'TX')
        ->set('providerDocuments.0.file', $deaPdf)
        // Add Provider Portal Login
        ->call('addPortalLoginRow')
        ->set('portalLogins.0.portal_name', 'CAQH ProView')
        ->set('portalLogins.0.username', 'coswald_md')
        ->set('portalLogins.0.password', 'CaqhPass#2026')
        ->call('save')
        ->assertHasNoErrors();

    $provider = ProviderDetails::where('npi', '1554433221')->firstOrFail();

    expect($provider->providerPracticeLocations()->count())->toBe(1)
        ->and(Location::where('practice_id', $practice->id)->where('name', 'Houston Heart Center')->exists())->toBeTrue()
        ->and(Document::where('provider_id', $provider->id)->where('title', 'Federal DEA Certificate')->exists())->toBeTrue();

    $providerLogin = $provider->portalLogins()->where('portal_name', 'CAQH ProView')->first();
    expect($providerLogin)->not->toBeNull()
        ->and($providerLogin->password)->toBe('CaqhPass#2026');
});
