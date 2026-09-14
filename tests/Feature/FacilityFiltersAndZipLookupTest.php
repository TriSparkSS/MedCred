<?php

use App\Livewire\Admin\Practices\PracticeContactsSection;
use App\Livewire\Admin\Practices\PracticeCreatePage;
use App\Livewire\Admin\Practices\PracticeDetailsPage;
use App\Livewire\Admin\Practices\PracticeLocationsSection;
use App\Livewire\Admin\Provider\ProviderListPage;
use App\Models\Admin;
use App\Models\Location;
use App\Models\Practice;
use App\Models\PracticeContact;
use App\Models\ProviderDetails;
use App\Models\ProviderPracticeLocation;
use App\Models\User;
use App\Services\ZipLookupService;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(AdminPermissionSeeder::class);
    $this->seed(AdminSeeder::class);
});

function facilityTestPractice(string $name, string $code, string $email): Practice
{
    return Practice::create([
        'legal_name' => $name,
        'client_code' => $code,
        'email' => $email,
        'status' => 'active',
        'user_id' => User::create([
            'name' => $name.' User',
            'email' => str_replace('@', '-user@', $email),
            'password' => 'password',
        ])->id,
    ]);
}

function facilityTestProvider(string $name, string $npi, string $email): ProviderDetails
{
    return ProviderDetails::create([
        'user_id' => User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
        ])->id,
        'npi' => $npi,
        'status' => 'approved',
        'practice' => 'Placeholder',
        'address' => '1 Test St',
        'city' => 'Austin',
        'state' => 'TX',
        'zip' => '78701',
    ]);
}

function facilityTestLocation(Practice $practice, string $name, bool $primary = false): Location
{
    return Location::create([
        'practice_id' => $practice->id,
        'name' => $name,
        'address1' => '100 '.$name.' St',
        'city' => 'Austin',
        'state' => 'TX',
        'zip_code' => '78701',
        'country' => 'United States',
        'status' => 'active',
        'is_primary' => $primary,
    ]);
}

function fakeZipLookup(?string $city = 'Beverly Hills', ?string $state = 'CA', ?string $county = 'Los Angeles', int $status = 200): void
{
    Http::fake([
        'api.zippopotam.us/*' => $status === 200
            ? Http::response([
                'places' => [[
                    'place name' => $city,
                    'state abbreviation' => $state,
                    'latitude' => '34.0901',
                    'longitude' => '-118.4065',
                ]],
            ])
            : Http::response('Not found', $status),
        'geo.fcc.gov/*' => Http::response([
            'results' => [
                ['county_name' => $county],
            ],
        ]),
    ]);
}

test('provider list practice filter includes assigned and location-linked providers', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practiceA = facilityTestPractice('Alpha Practice', 'ALP', 'alpha-practice@example.com');
    $practiceB = facilityTestPractice('Beta Practice', 'BET', 'beta-practice@example.com');
    $locA = facilityTestLocation($practiceA, 'Alpha Main', true);
    $locB = facilityTestLocation($practiceB, 'Beta Main', true);

    $assigned = facilityTestProvider('Dr. Assigned', '1111111111', 'assigned@example.com');
    $assigned->practices()->attach($practiceA->id, ['primary_flag' => true]);

    $locationLinked = facilityTestProvider('Dr. Location Linked', '2222222222', 'location-linked@example.com');
    ProviderPracticeLocation::create([
        'provider_id' => $locationLinked->id,
        'practice_id' => $practiceA->id,
        'location_id' => $locA->id,
        'is_primary' => true,
    ]);

    $other = facilityTestProvider('Dr. Other Practice', '3333333333', 'other-practice@example.com');
    $other->practices()->attach($practiceB->id, ['primary_flag' => true]);
    ProviderPracticeLocation::create([
        'provider_id' => $other->id,
        'practice_id' => $practiceB->id,
        'location_id' => $locB->id,
        'is_primary' => true,
    ]);

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderListPage::class)
        ->set('filterPractice', (string) $practiceA->id)
        ->assertSee('Dr. Assigned')
        ->assertSee('Dr. Location Linked')
        ->assertDontSee('Dr. Other Practice');
});

test('provider list location filter narrows to providers at that site and clears when practice changes', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = facilityTestPractice('Multi Site', 'MLS', 'multi-site@example.com');
    $main = facilityTestLocation($practice, 'Main Clinic', true);
    $north = facilityTestLocation($practice, 'North Clinic');

    $mainProvider = facilityTestProvider('Dr. Main Site', '4444444444', 'main-site@example.com');
    $northProvider = facilityTestProvider('Dr. North Site', '5555555555', 'north-site@example.com');

    $mainProvider->practices()->attach($practice->id, ['primary_flag' => true]);
    $northProvider->practices()->attach($practice->id, ['primary_flag' => false]);

    ProviderPracticeLocation::create([
        'provider_id' => $mainProvider->id,
        'practice_id' => $practice->id,
        'location_id' => $main->id,
        'is_primary' => true,
    ]);
    ProviderPracticeLocation::create([
        'provider_id' => $northProvider->id,
        'practice_id' => $practice->id,
        'location_id' => $north->id,
        'is_primary' => true,
    ]);

    $otherPractice = facilityTestPractice('Other Site Practice', 'OSP', 'other-site@example.com');

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderListPage::class)
        ->set('filterPractice', (string) $practice->id)
        ->set('filterLocation', (string) $main->id)
        ->assertSee('Dr. Main Site')
        ->assertDontSee('Dr. North Site')
        ->set('filterPractice', (string) $otherPractice->id)
        ->assertSet('filterLocation', '');
});

test('practice location and contact can be created from the details locations tab', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = facilityTestPractice('Details Practice', 'DTP', 'details-practice@example.com');

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeDetailsPage::class, ['practice' => $practice->id, 'activeTab' => 'locations'])
        ->assertSeeLivewire(PracticeLocationsSection::class)
        ->assertSeeLivewire(PracticeContactsSection::class);

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeLocationsSection::class, ['practiceId' => $practice->id])
        ->call('openCreateModal')
        ->set('formData.name', 'West Clinic')
        ->set('formData.address1', '500 West St')
        ->set('formData.city', 'Austin')
        ->set('formData.state', 'TX')
        ->set('formData.zip_code', '78701')
        ->set('formData.country', 'United States')
        ->set('formData.status', 'active')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeContactsSection::class, ['practiceId' => $practice->id])
        ->call('openCreateModal')
        ->set('formData.name', 'Jane Office')
        ->set('formData.title', 'Office Manager')
        ->set('formData.email', 'jane@example.com')
        ->call('save')
        ->assertHasNoErrors();

    expect(Location::where('practice_id', $practice->id)->where('name', 'West Clinic')->exists())->toBeTrue();
    expect(PracticeContact::where('practice_id', $practice->id)->where('name', 'Jane Office')->exists())->toBeTrue();
});

test('practice edit page mounts location and contact sections outside the parent form', function () {
    $blade = file_get_contents(resource_path('views/livewire/admin/practices/practice-edit-page.blade.php'));
    $formClose = strpos($blade, '</form>');
    $locations = strpos($blade, 'practice-locations-section');
    $contacts = strpos($blade, 'practice-contacts-section');

    expect($formClose)->not->toBeFalse()
        ->and($locations)->toBeGreaterThan($formClose)
        ->and($contacts)->toBeGreaterThan($formClose);
});

test('creating a practice redirects to the details locations tab', function () {
    $this->seed(RoleSeeder::class);

    $admin = Admin::where('username', 'superadmin')->firstOrFail();

    $component = Livewire::actingAs($admin, 'admin')
        ->test(PracticeCreatePage::class)
        ->set('formData.legal_name', 'New Redirect Practice')
        ->set('formData.client_code', 'NRP')
        ->set('formData.email', 'redirect-practice@example.com')
        ->set('formData.status', 'pending')
        ->set('userData.password', 'secret123')
        ->set('addressData.address1', '10 Main St')
        ->set('addressData.city', 'Austin')
        ->set('addressData.state', 'TX')
        ->set('addressData.zip_code', '78701')
        ->set('addressData.country', 'United States')
        ->call('save')
        ->assertHasNoErrors();

    $practice = Practice::where('email', 'redirect-practice@example.com')->firstOrFail();

    $component->assertRedirect(route('admin.practices.show', [
        'practice' => $practice->id,
        'tab' => 'locations',
    ]));
});

test('zip lookup service fills city state and county from faked apis', function () {
    fakeZipLookup();

    $result = app(ZipLookupService::class)->lookup('90210');

    expect($result['city'])->toBe('Beverly Hills')
        ->and($result['state'])->toBe('CA')
        ->and($result['county'])->toBe('Los Angeles');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.zippopotam.us/us/90210'));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'geo.fcc.gov'));
});

test('location zip lookup fills city state and county', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = facilityTestPractice('Zip Practice', 'ZIP', 'zip-practice@example.com');

    fakeZipLookup();

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeLocationsSection::class, ['practiceId' => $practice->id])
        ->call('openCreateModal')
        ->set('formData.zip_code', '90210')
        ->call('lookupZip', 'formData')
        ->assertSet('formData.city', 'Beverly Hills')
        ->assertSet('formData.state', 'CA')
        ->assertSet('formData.county', 'Los Angeles');
});

test('unknown zip shows a manual-entry notice', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = facilityTestPractice('Unknown Zip Practice', 'UZP', 'unknown-zip@example.com');

    Http::fake(function ($request) {
        if (str_contains($request->url(), 'zippopotam')) {
            return Http::response('Not found', 404);
        }

        return Http::response(['results' => []], 200);
    });

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeLocationsSection::class, ['practiceId' => $practice->id])
        ->call('openCreateModal')
        ->set('formData.zip_code', '00000')
        ->call('lookupZip', 'formData')
        ->assertSet('zipLookupMessages.formData', 'Zip code not found. Enter city, state, and county manually.');
});
