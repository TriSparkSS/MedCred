<?php

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Livewire\Admin\Documents\EntityDocumentsSection;
use App\Livewire\Admin\Practices\PracticeCreatePage;
use App\Livewire\Admin\Practices\PracticeEditPage;
use App\Livewire\Admin\Provider\ProviderCreatePage;
use App\Livewire\Admin\Provider\ProviderEditPage;
use App\Livewire\Admin\Task\TaskBoardPage;
use App\Livewire\Admin\Task\TaskDetailDrawer;
use App\Models\Admin;
use App\Models\CredentialingCase;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Location;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\ProviderPracticeLocation;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
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

function createTestPractice1022(string $code = 'P1022'): Practice
{
    return Practice::create([
        'legal_name' => 'Practice '.$code,
        'client_code' => $code,
        'email' => strtolower($code).'@example.com',
        'status' => 'active',
        'user_id' => User::create([
            'name' => $code.' User',
            'email' => strtolower($code).'-u-'.uniqid().'@example.com',
            'password' => 'password',
        ])->id,
    ]);
}

test('item 1: edit practice page embeds bank documents section with Bank Document type', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = createTestPractice1022('BNK1');

    $bankType = DocumentType::where('name', 'Bank Document')->first();
    expect($bankType)->not->toBeNull()
        ->and($bankType->is_state_specific)->toBeFalse();

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeEditPage::class, ['practice' => $practice->id])
        ->assertOk()
        ->assertSee('Bank Details')
        ->assertSee('Bank Documents')
        ->assertSeeLivewire(EntityDocumentsSection::class);

    $bankFile = UploadedFile::fake()->create('voided-check.pdf', 64, 'application/pdf');

    Livewire::actingAs($admin, 'admin')
        ->test(EntityDocumentsSection::class, [
            'practiceId' => $practice->id,
            'lockedTypeName' => 'Bank Document',
            'heading' => 'Bank Documents',
            'showProfileLink' => false,
        ])
        ->call('openUploadModal')
        ->set('formData.title', 'Voided Bank Check')
        ->set('uploadFile', $bankFile)
        ->call('saveDocument')
        ->assertHasNoErrors()
        ->assertSee('Voided Bank Check');

    $doc = Document::where('practice_id', $practice->id)
        ->where('document_type_id', $bankType->id)
        ->first();

    expect($doc)->not->toBeNull()
        ->and($doc->title)->toBe('Voided Bank Check');
});

test('item 2: add provider page allows adding multiple service locations from practice addresses and new locations', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = createTestPractice1022('SLOC');

    $billingAddress = $practice->addresses()->create([
        'type' => 'billing',
        'address1' => '500 Billing Way',
        'city' => 'Austin',
        'state' => 'TX',
        'zip_code' => '78701',
        'country' => 'United States',
        'status' => 'active',
    ]);

    $facility = Location::create([
        'practice_id' => $practice->id,
        'name' => 'North Clinic Facility',
        'address1' => '900 North Mopac',
        'city' => 'Austin',
        'state' => 'TX',
        'zip_code' => '78759',
        'country' => 'United States',
        'status' => 'active',
        'is_primary' => true,
    ]);

    $component = Livewire::actingAs($admin, 'admin')
        ->test(ProviderCreatePage::class)
        ->assertSee('Service Locations')
        ->assertSee('Add Location')
        ->set('practiceId', (string) $practice->id)
        // Add 1st location: existing facility
        ->call('openLocationModal')
        ->set('locationForm.practice_id', (string) $practice->id)
        ->set('locationForm.location_key', 'location:'.$facility->id)
        ->set('locationForm.is_primary', true)
        ->call('addServiceLocation')
        ->assertHasNoErrors()
        ->assertSee('North Clinic Facility')
        // Add 2nd location: existing practice billing address
        ->call('openLocationModal')
        ->set('locationForm.practice_id', (string) $practice->id)
        ->set('locationForm.location_key', 'address:'.$billingAddress->id)
        ->call('addServiceLocation')
        ->assertHasNoErrors()
        ->assertSee('500 Billing Way')
        // Add 3rd location: brand new location entered manually
        ->call('openLocationModal')
        ->set('locationForm.mode', 'new')
        ->set('locationForm.name', 'South Satellite Office')
        ->set('locationForm.address1', '300 South Lamar')
        ->set('locationForm.city', 'Austin')
        ->set('locationForm.state', 'TX')
        ->set('locationForm.zip_code', '78704')
        ->call('addServiceLocation')
        ->assertHasNoErrors()
        ->assertSee('South Satellite Office')
        // Complete required provider fields and save
        ->set('userData.name', 'Dr. Multi Location')
        ->set('userData.email', 'dr-multi-loc@example.com')
        ->set('userData.password', 'secret123')
        ->set('formData.npi', '1444555666')
        ->set('formData.status', 'active')
        ->call('save')
        ->assertHasNoErrors();

    $provider = ProviderDetails::where('npi', '1444555666')->firstOrFail();
    $links = ProviderPracticeLocation::with('location')
        ->where('provider_id', $provider->id)
        ->get();

    expect($links)->toHaveCount(3)
        ->and($links->where('is_primary', true))->toHaveCount(1)
        ->and($links->firstWhere('is_primary', true)?->location?->name)->toBe('North Clinic Facility')
        ->and($links->pluck('location.address1')->all())->toContain('900 North Mopac', '500 Billing Way', '300 South Lamar');
});

test('item 3: zip code lookup auto-populates city and state on provider home address and service location modal', function () {
    Http::fake([
        'api.zippopotam.us/us/78701' => Http::response([
            'places' => [
                [
                    'place name' => 'Austin',
                    'state abbreviation' => 'TX',
                ],
            ],
        ], 200),
        'api.zippopotam.us/us/90210' => Http::response([
            'places' => [
                [
                    'place name' => 'Beverly Hills',
                    'state abbreviation' => 'CA',
                ],
            ],
        ], 200),
    ]);

    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = createTestPractice1022('ZIP1');

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderCreatePage::class)
        ->set('practiceId', (string) $practice->id)
        ->set('formData.home_zip', '78701')
        ->call('lookupZip', 'formData', 'home_zip', 'home_city', 'home_state')
        ->assertSet('formData.home_city', 'Austin')
        ->assertSet('formData.home_state', 'TX')
        ->call('openLocationModal')
        ->set('locationForm.mode', 'new')
        ->set('locationForm.zip_code', '90210')
        ->call('lookupZip', 'locationForm', 'zip_code')
        ->assertSet('locationForm.city', 'Beverly Hills')
        ->assertSet('locationForm.state', 'CA');

    $provider = ProviderDetails::create([
        'user_id' => User::create(['name' => 'Dr. Zip Edit', 'email' => 'dr-zip-edit@example.com', 'password' => 'password'])->id,
        'npi' => '1333444555',
        'status' => 'active',
    ]);
    $provider->practices()->syncWithoutDetaching([$practice->id => ['primary_flag' => true]]);

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderEditPage::class, ['provider' => $provider->id])
        ->set('formData.home_zip', '90210')
        ->call('lookupZip', 'formData', 'home_zip', 'home_city', 'home_state')
        ->assertSet('formData.home_city', 'Beverly Hills')
        ->assertSet('formData.home_state', 'CA');
});

test('item 4: add provider and add practice pages include autocomplete off and new-password attributes on email and password inputs', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderCreatePage::class)
        ->assertSeeHtml('name="new_provider_email"')
        ->assertSeeHtml('autocomplete="off"')
        ->assertSeeHtml('name="new_provider_password"')
        ->assertSeeHtml('autocomplete="new-password"');

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeCreatePage::class)
        ->assertSeeHtml('name="new_practice_email"')
        ->assertSeeHtml('autocomplete="off"')
        ->assertSeeHtml('name="new_practice_password"')
        ->assertSeeHtml('autocomplete="new-password"');
});

test('items 5 and 6: task board defaults to open tasks list without open applications table, links filters, and hyperlinks case in list and drawer', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practiceA = createTestPractice1022('TSKA');
    $practiceB = createTestPractice1022('TSKB');

    $providerA = ProviderDetails::create([
        'user_id' => User::create(['name' => 'Dr. Alice Task', 'email' => 'alice-task@example.com', 'password' => 'password'])->id,
        'npi' => '1222111000',
        'status' => 'active',
    ]);
    $providerA->practices()->syncWithoutDetaching([$practiceA->id => ['primary_flag' => true]]);

    $providerB = ProviderDetails::create([
        'user_id' => User::create(['name' => 'Dr. Bob Task', 'email' => 'bob-task@example.com', 'password' => 'password'])->id,
        'npi' => '1222111001',
        'status' => 'active',
    ]);
    $providerB->practices()->syncWithoutDetaching([$practiceB->id => ['primary_flag' => true]]);

    $statusId = Status::where('name', 'At Payer')->firstOrFail()->id;
    $payer = Payer::create(['name' => 'UnitedHealthcare', 'is_active' => true]);

    $caseA = CredentialingCase::create([
        'practice_id' => $practiceA->id,
        'provider_id' => $providerA->id,
        'payer_id' => $payer->id,
        'status_id' => $statusId,
        'state' => 'TX',
    ]);

    $openTaskA = Task::create([
        'title' => 'Verify Alice Payer Packet',
        'credentialing_case_id' => $caseA->id,
        'payer_id' => $payer->id,
        'status' => TaskStatus::Open->value,
        'task_type' => TaskType::FollowUp->value,
        'due_date' => now()->addDays(2)->toDateString(),
    ]);

    $openTaskB = Task::create([
        'title' => 'Collect Bob License Renewal',
        'provider_id' => $providerB->id,
        'status' => TaskStatus::Open->value,
        'task_type' => TaskType::Manual->value,
        'due_date' => now()->addDays(3)->toDateString(),
    ]);

    $completedTask = Task::create([
        'title' => 'Completed Historical Task',
        'credentialing_case_id' => $caseA->id,
        'status' => TaskStatus::Completed->value,
        'task_type' => TaskType::Manual->value,
        'completed_at' => now(),
    ]);

    $caseUrl = route('admin.credentials.show', $caseA);

    // Default view is 'list', shows open tasks only, does not show 'Open applications', shows Practice, Provider, and Case link
    Livewire::actingAs($admin, 'admin')
        ->test(TaskBoardPage::class)
        ->assertSet('viewMode', 'list')
        ->assertDontSee('Open applications')
        ->assertSee('Verify Alice Payer Packet')
        ->assertSee('Collect Bob License Renewal')
        ->assertDontSee('Completed Historical Task')
        ->assertSee($practiceA->legal_name)
        ->assertSee('Dr. Alice Task')
        ->assertSeeHtml($caseUrl)
        // Filter by Practice A
        ->set('filterPracticeId', (string) $practiceA->id)
        ->assertSee('Verify Alice Payer Packet')
        ->assertDontSee('Collect Bob License Renewal')
        // Filter by Practice B (matches provider-linked task without case)
        ->set('filterPracticeId', (string) $practiceB->id)
        ->assertSee('Collect Bob License Renewal')
        ->assertDontSee('Verify Alice Payer Packet')
        // Reset practice filter and filter by Provider A (matches case-linked task where task.provider_id is null)
        ->set('filterPracticeId', '')
        ->set('filterProviderId', (string) $providerA->id)
        ->assertSee('Verify Alice Payer Packet')
        ->assertDontSee('Collect Bob License Renewal')
        // Completed chip reveals completed tasks
        ->set('filterProviderId', '')
        ->set('filterPracticeId', '')
        ->call('setChip', 'completed')
        ->assertSee('Completed Historical Task');

    // Item 6: TaskDetailDrawer shows clickable application hyperlink
    Livewire::actingAs($admin, 'admin')
        ->test(TaskDetailDrawer::class)
        ->call('open', $openTaskA->id)
        ->assertSet('show', true)
        ->assertSee($caseA->case_number)
        ->assertSeeHtml($caseUrl)
        ->assertSee($practiceA->legal_name)
        ->assertSee('Dr. Alice Task');
});
