<?php

use App\Livewire\Admin\Documents\EntityDocumentsSection;
use App\Livewire\Admin\Practices\PracticeCreatePage;
use App\Livewire\Admin\Practices\PracticeDetailsPage;
use App\Livewire\Admin\Practices\PracticeEditPage;
use App\Models\Admin;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Practice;
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
    $this->seed(RoleSeeder::class);
    $this->seed(MasterDataSeeder::class);
    Storage::fake('public');
});

function licenseDocumentsPractice(array $overrides = []): Practice
{
    return Practice::create([
        'legal_name' => 'License Docs Practice',
        'client_code' => 'LDP',
        'email' => 'license-docs-practice@example.com',
        'status' => 'active',
        'license_name' => 'State facility license',
        'license_number' => 'TX-7788',
        'license_effective_date' => '2026-03-01',
        'license_expiry_date' => '2027-03-01',
        'user_id' => User::create([
            'name' => 'License Docs User',
            'email' => 'license-docs-user@example.com',
            'password' => 'password',
        ])->id,
        ...$overrides,
    ]);
}

test('practice create page shows the License & Documents section and fields', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeCreatePage::class)
        ->assertSee('License & Documents')
        ->assertSee('License / document name')
        ->assertSee('License Number')
        ->assertSee('Effective date')
        ->assertSee('Expiration');
});

test('practice edit page shows the unified License & Documents section without duplicate single-file inputs', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = licenseDocumentsPractice();

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeEditPage::class, ['practice' => $practice->id])
        ->assertSee('License & Documents')
        ->assertDontSee('License / document name')
        ->assertSee('Number')
        ->assertSee('Effective')
        ->assertSee('Expiration')
        ->assertSeeLivewire(EntityDocumentsSection::class);
});

test('practice details overview and documents tab show License & Documents with name number and dates', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = licenseDocumentsPractice();

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeDetailsPage::class, ['practice' => $practice->id, 'activeTab' => 'overview'])
        ->assertSee('License & Documents')
        ->assertSee('State facility license')
        ->assertSee('TX-7788')
        ->assertSee('03/01/2026')
        ->assertSee('03/01/2027');

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeDetailsPage::class, ['practice' => $practice->id, 'activeTab' => 'documents'])
        ->assertSee('License & Documents')
        ->assertSee('State facility license')
        ->assertSee('TX-7788')
        ->assertSee('License / document name')
        ->assertSee('License Number')
        ->assertSee('Effective date')
        ->assertSee('Expiration')
        ->assertSeeLivewire(EntityDocumentsSection::class);
});

test('practice create persists license fields onto the practice and a document', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $file = UploadedFile::fake()->create('facility-license.pdf', 80, 'application/pdf');

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeCreatePage::class)
        ->set('formData.legal_name', 'Created License Practice')
        ->set('formData.client_code', 'CLP')
        ->set('formData.email', 'created-license@example.com')
        ->set('formData.status', 'pending')
        ->set('formData.license_name', 'Clinic facility license')
        ->set('formData.license_number', 'CL-2200')
        ->set('formData.license_effective_date', '2026-04-15')
        ->set('formData.license_expiry_date', '2027-04-15')
        ->set('userData.password', 'secret123')
        ->set('addressData.address1', '10 Main St')
        ->set('addressData.city', 'Austin')
        ->set('addressData.state', 'TX')
        ->set('addressData.zip_code', '78701')
        ->set('addressData.country', 'United States')
        ->set('document', $file)
        ->call('save')
        ->assertHasNoErrors();

    $practice = Practice::where('client_code', 'CLP')->firstOrFail();

    expect($practice->license_name)->toBe('Clinic facility license')
        ->and($practice->license_number)->toBe('CL-2200')
        ->and($practice->license_effective_date?->toDateString())->toBe('2026-04-15')
        ->and($practice->license_expiry_date?->toDateString())->toBe('2027-04-15');

    $document = Document::where('practice_id', $practice->id)->firstOrFail();

    expect($document->title)->toBe('Clinic facility license')
        ->and($document->document_number)->toBe('CL-2200')
        ->and($document->effective_date?->toDateString())->toBe('2026-04-15')
        ->and($document->expiry_date?->toDateString())->toBe('2027-04-15')
        ->and($document->documentType?->name)->toBe(Practice::LICENSE_DOCUMENT_TYPE);
});

test('practice documents table shows persisted number effective and expiration', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = licenseDocumentsPractice();
    $type = DocumentType::firstOrCreate(
        ['name' => Practice::LICENSE_DOCUMENT_TYPE],
        ['is_active' => true, 'is_state_specific' => false]
    );

    Document::create([
        'practice_id' => $practice->id,
        'document_type_id' => $type->id,
        'title' => 'State facility license',
        'document_number' => 'TX-7788',
        'effective_date' => '2026-03-01',
        'expiry_date' => '2027-03-01',
        'status' => 'active',
    ]);

    Livewire::actingAs($admin, 'admin')
        ->test(EntityDocumentsSection::class, [
            'practiceId' => $practice->id,
            'heading' => 'License & Documents',
        ])
        ->assertSee('License & Documents')
        ->assertSee('State facility license')
        ->assertSee('TX-7788')
        ->assertSee('03/01/2026')
        ->assertSee('03/01/2027')
        ->assertSee('Number')
        ->assertSee('Effective')
        ->assertSee('Expiration');
});

test('uploading a practice license document updates practice license fields', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = licenseDocumentsPractice([
        'license_name' => null,
        'license_number' => null,
        'license_effective_date' => null,
        'license_expiry_date' => null,
    ]);
    $type = DocumentType::where('name', Practice::LICENSE_DOCUMENT_TYPE)->firstOrFail();
    $file = UploadedFile::fake()->create('synced-license.pdf', 40, 'application/pdf');

    Livewire::actingAs($admin, 'admin')
        ->test(EntityDocumentsSection::class, ['practiceId' => $practice->id])
        ->call('openUploadModal')
        ->set('formData.title', 'Synced facility license')
        ->set('formData.document_number', 'SYNC-91')
        ->set('formData.document_type_id', $type->id)
        ->set('formData.effective_date', '2026-05-01')
        ->set('formData.expiry_date', '2027-05-01')
        ->set('uploadFile', $file)
        ->call('saveDocument')
        ->assertHasNoErrors();

    $practice->refresh();

    expect($practice->license_name)->toBe('Synced facility license')
        ->and($practice->license_number)->toBe('SYNC-91')
        ->and($practice->license_effective_date?->toDateString())->toBe('2026-05-01')
        ->and($practice->license_expiry_date?->toDateString())->toBe('2027-05-01');
});

test('editing a practice license document updates name number and dates on the practice', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = licenseDocumentsPractice();
    $type = DocumentType::where('name', Practice::LICENSE_DOCUMENT_TYPE)->firstOrFail();
    $file = UploadedFile::fake()->create('edit-license.pdf', 40, 'application/pdf');

    $document = Document::createWithFile([
        'practice_id' => $practice->id,
        'document_type_id' => $type->id,
        'title' => 'State facility license',
        'document_number' => 'TX-7788',
        'effective_date' => '2026-03-01',
        'expiry_date' => '2027-03-01',
    ], $file, $admin->id);

    Livewire::actingAs($admin, 'admin')
        ->test(EntityDocumentsSection::class, ['practiceId' => $practice->id])
        ->call('openEditModal', $document->id)
        ->assertSet('showModal', true)
        ->assertSet('formData.title', 'State facility license')
        ->assertSet('formData.document_number', 'TX-7788')
        ->set('formData.title', 'Updated facility license')
        ->set('formData.document_number', 'TX-9900')
        ->set('formData.effective_date', '2026-06-01')
        ->set('formData.expiry_date', '2027-06-01')
        ->call('saveDocument')
        ->assertHasNoErrors();

    $document->refresh();
    $practice->refresh();

    expect($document->title)->toBe('Updated facility license')
        ->and($document->document_number)->toBe('TX-9900')
        ->and($document->effective_date?->toDateString())->toBe('2026-06-01')
        ->and($document->expiry_date?->toDateString())->toBe('2027-06-01')
        ->and($practice->license_name)->toBe('Updated facility license')
        ->and($practice->license_number)->toBe('TX-9900')
        ->and($practice->license_effective_date?->toDateString())->toBe('2026-06-01')
        ->and($practice->license_expiry_date?->toDateString())->toBe('2027-06-01');
});
