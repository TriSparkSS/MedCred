<?php

use App\Livewire\Admin\Credential\CredentialDetailsPage;
use App\Livewire\Admin\Credential\CredentialListPage;
use App\Livewire\Admin\Documents\EntityDocumentsSection;
use App\Livewire\Admin\Provider\ProviderCreatePage;
use App\Livewire\Admin\Provider\ProviderCredentialsSection;
use App\Livewire\Admin\Provider\ProviderDetailsPage;
use App\Livewire\Admin\Provider\ProviderEditPage;
use App\Livewire\Admin\Provider\ProviderLocationsSection;
use App\Models\Admin;
use App\Models\CredentialingCase;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Location;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\Status;
use App\Models\Task;
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
});

function screenshotPractice(): Practice
{
    $practice = Practice::create([
        'legal_name' => 'Screenshot Medical Group',
        'client_code' => 'SMG',
        'email' => 'smg@example.com',
        'status' => 'active',
        'user_id' => User::create([
            'name' => 'SMG User',
            'email' => 'smg-user@example.com',
            'password' => 'password',
        ])->id,
    ]);

    $practice->addresses()->create([
        'type' => 'mailing',
        'address1' => '500 Mail Rd',
        'city' => 'Austin',
        'state' => 'TX',
        'zip_code' => '78701',
        'country' => 'United States',
        'status' => 'active',
    ]);

    Location::create([
        'practice_id' => $practice->id,
        'name' => 'Main Clinic',
        'address1' => '500 Mail Rd',
        'city' => 'Austin',
        'state' => 'TX',
        'zip_code' => '78701',
        'country' => 'United States',
        'status' => 'active',
        'is_primary' => true,
    ]);

    return $practice->fresh(['mailingAddress', 'primaryLocation']);
}

function screenshotProvider(?Practice $practice = null): ProviderDetails
{
    $practice ??= screenshotPractice();

    $provider = ProviderDetails::create([
        'user_id' => User::create([
            'name' => 'Dr. Screenshot',
            'email' => 'dr-screenshot-'.uniqid().'@example.com',
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

function screenshotCase(?Practice $practice = null, ?ProviderDetails $provider = null): CredentialingCase
{
    $practice ??= screenshotPractice();
    $provider ??= screenshotProvider($practice);

    return CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => Payer::create(['name' => 'Screenshot Payer '.uniqid(), 'is_active' => true])->id,
        'status_id' => Status::where('name', 'At Payer')->firstOrFail()->id,
        'state' => 'TX',
    ]);
}

test('add provider fills mailing address from selected practice and links the practice', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = screenshotPractice();

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderCreatePage::class)
        ->set('practiceId', (string) $practice->id)
        ->assertSet('formData.practice', 'Screenshot Medical Group')
        ->assertSet('formData.address', '500 Mail Rd')
        ->assertSet('formData.city', 'Austin')
        ->assertSet('formData.state', 'TX')
        ->assertSet('formData.zip', '78701')
        ->set('userData.name', 'Dr. Linked')
        ->set('userData.email', 'dr-linked@example.com')
        ->set('userData.password', 'secret123')
        ->set('formData.npi', '1444444444')
        ->set('formData.status', 'inactive')
        ->call('save')
        ->assertHasNoErrors();

    $provider = ProviderDetails::where('npi', '1444444444')->firstOrFail();

    expect($provider->practice)->toBe('Screenshot Medical Group')
        ->and($provider->practices()->where('practices.id', $practice->id)->exists())->toBeTrue();
});

test('provider details keeps location actions and points credentials to edit', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $provider = screenshotProvider();

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderDetailsPage::class, ['provider' => $provider])
        ->assertDontSeeLivewire(ProviderCredentialsSection::class)
        ->assertSeeLivewire(ProviderLocationsSection::class)
        ->assertSee('Edit credentials')
        ->assertSee('Add Location');

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderEditPage::class, ['provider' => $provider->id])
        ->set('credentialRows.0.credential_type', 'license')
        ->set('credentialRows.0.state', 'TX')
        ->set('credentialRows.0.number', 'TX-900')
        ->call('save')
        ->assertHasNoErrors();

    expect($provider->credentials()->where('number', 'TX-900')->exists())->toBeTrue();
});

test('malpractice documents section uploads only malpractice insurance files', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $provider = screenshotProvider();
    $type = DocumentType::where('name', 'Malpractice Insurance')->firstOrFail();
    Storage::fake('public');
    $file = UploadedFile::fake()->create('malp.pdf', 40, 'application/pdf');

    Livewire::actingAs($admin, 'admin')
        ->test(EntityDocumentsSection::class, [
            'providerId' => $provider->id,
            'lockedTypeName' => 'Malpractice Insurance',
            'heading' => 'Malpractice Documents',
        ])
        ->assertSee('Malpractice Documents')
        ->call('openUploadModal')
        ->set('uploadFile', $file)
        ->call('saveDocument')
        ->assertHasNoErrors();

    $document = Document::where('provider_id', $provider->id)->firstOrFail();
    expect($document->document_type_id)->toBe($type->id)
        ->and($document->title)->toBe('Malpractice Insurance');
});

test('credentialing tracker bulk assigns selected cases', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $assignee = Admin::where('username', 'executive')->firstOrFail();
    $practice = screenshotPractice();
    $first = screenshotCase($practice);
    $second = screenshotCase($practice, screenshotProvider($practice));

    Livewire::actingAs($admin, 'admin')
        ->test(CredentialListPage::class)
        ->set('selectedCaseIds', [(string) $first->id, (string) $second->id])
        ->set('bulkAssigneeId', (string) $assignee->id)
        ->call('bulkAssignSelected')
        ->assertHasNoErrors();

    expect($first->fresh()->assigned_admin_id)->toBe($assignee->id)
        ->and($second->fresh()->assigned_admin_id)->toBe($assignee->id);
});

test('case details shows notes and creates a follow-up from the details tab', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $case = screenshotCase();

    Livewire::actingAs($admin, 'admin')
        ->test(CredentialDetailsPage::class, ['case' => $case])
        ->set('newNote', 'Called payer about missing roster.')
        ->call('addNote')
        ->assertSee('Called payer about missing roster.')
        ->set('newTaskDueDate', now()->addDays(5)->toDateString())
        ->call('createFollowUpFromDetails')
        ->assertHasNoErrors();

    expect($case->fresh()->next_follow_up_date?->toDateString())->toBe(now()->addDays(5)->toDateString());
    expect(Task::where('credentialing_case_id', $case->id)->where('title', 'Follow-up for '.$case->case_number)->exists())->toBeTrue();
});

test('edit provider address dropdown fills from a selected service location', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = screenshotPractice();
    $west = Location::create([
        'practice_id' => $practice->id,
        'name' => 'West Clinic',
        'address1' => '90 West Blvd',
        'city' => 'Houston',
        'state' => 'TX',
        'zip_code' => '77001',
        'country' => 'United States',
        'status' => 'active',
        'is_primary' => false,
    ]);
    $provider = screenshotProvider($practice);

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderEditPage::class, ['provider' => $provider->id])
        ->assertSet('practiceId', (string) $practice->id)
        ->assertSee('Primary')
        ->assertSee('West Clinic')
        ->set('addressKey', 'location:'.$west->id)
        ->assertSet('formData.address', '90 West Blvd')
        ->assertSet('formData.city', 'Houston')
        ->assertSet('formData.zip', '77001')
        ->call('save')
        ->assertHasNoErrors();

    expect($provider->fresh()->address)->toBe('90 West Blvd')
        ->and($provider->fresh()->city)->toBe('Houston');
});
