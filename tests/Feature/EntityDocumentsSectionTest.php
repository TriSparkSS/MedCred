<?php

use App\Livewire\Admin\Documents\DocumentListPage;
use App\Livewire\Admin\Documents\EntityDocumentsSection;
use App\Livewire\Admin\Practices\PracticeDetailsPage;
use App\Livewire\Admin\Practices\PracticeEditPage;
use App\Livewire\Admin\Provider\ProviderDetailsPage;
use App\Livewire\Admin\Provider\ProviderEditPage;
use App\Models\Admin;
use App\Models\CredentialingCase;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\MasterDataSeeder;
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
    Storage::fake('public');
});

function documentsTestPractice(): Practice
{
    return Practice::create([
        'legal_name' => 'Docs Practice',
        'client_code' => 'DCP',
        'email' => 'docs-practice@example.com',
        'status' => 'active',
        'user_id' => User::create([
            'name' => 'Docs Practice User',
            'email' => 'docs-practice-user@example.com',
            'password' => 'password',
        ])->id,
    ]);
}

function documentsTestProvider(): ProviderDetails
{
    return ProviderDetails::create([
        'user_id' => User::create([
            'name' => 'Dr. Docs',
            'email' => 'dr-docs@example.com',
            'password' => 'password',
        ])->id,
        'npi' => '1777777777',
        'status' => 'active',
        'practice' => 'Docs Practice',
        'address' => '1 Docs St',
        'city' => 'Austin',
        'state' => 'TX',
        'zip' => '78701',
    ]);
}

test('provider documents section upload stores provider_id and appears in the hub', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $provider = documentsTestProvider();
    $type = DocumentType::where('name', 'Malpractice Insurance')->firstOrFail();
    $file = UploadedFile::fake()->create('malpractice.pdf', 80, 'application/pdf');

    Livewire::actingAs($admin, 'admin')
        ->test(EntityDocumentsSection::class, ['providerId' => $provider->id])
        ->call('openUploadModal')
        ->set('formData.title', 'Malpractice Certificate')
        ->set('formData.document_type_id', $type->id)
        ->set('uploadFile', $file)
        ->call('saveDocument')
        ->assertHasNoErrors();

    $document = Document::where('provider_id', $provider->id)->firstOrFail();
    expect($document->title)->toBe('Malpractice Certificate')
        ->and($document->practice_id)->toBeNull();

    Livewire::actingAs($admin, 'admin')
        ->test(DocumentListPage::class)
        ->set('filterProvider', (string) $provider->id)
        ->assertSee('Malpractice Certificate');
});

test('practice documents section upload stores practice_id and appears in the hub', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = documentsTestPractice();
    $type = DocumentType::where('name', 'W-9')->firstOrFail();
    $file = UploadedFile::fake()->create('w9.pdf', 80, 'application/pdf');

    Livewire::actingAs($admin, 'admin')
        ->test(EntityDocumentsSection::class, ['practiceId' => $practice->id])
        ->call('openUploadModal')
        ->set('formData.title', 'Practice W-9')
        ->set('formData.document_number', 'W9-116')
        ->set('formData.document_type_id', $type->id)
        ->set('formData.effective_date', '2026-01-01')
        ->set('formData.expiry_date', '2027-01-01')
        ->set('uploadFile', $file)
        ->call('saveDocument')
        ->assertHasNoErrors();

    $document = Document::where('practice_id', $practice->id)->firstOrFail();
    expect($document->title)->toBe('Practice W-9')
        ->and($document->document_number)->toBe('W9-116')
        ->and($document->effective_date?->toDateString())->toBe('2026-01-01')
        ->and($document->expiry_date?->toDateString())->toBe('2027-01-01')
        ->and($document->provider_id)->toBeNull();

    Livewire::actingAs($admin, 'admin')
        ->test(DocumentListPage::class)
        ->set('filterPractice', (string) $practice->id)
        ->assertSee('Practice W-9');
});

test('provider and practice details pages have dedicated documents sections linked to the hub', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $provider = documentsTestProvider();
    $practice = documentsTestPractice();

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderDetailsPage::class, ['provider' => $provider, 'activeTab' => 'documents'])
        ->assertSee('Provider Documents')
        ->assertSee('Open in Document Hub')
        ->assertSee('Upload Document')
        ->assertSee(route('admin.documents', ['provider' => $provider->id], false));

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeDetailsPage::class, ['practice' => $practice->id, 'activeTab' => 'documents'])
        ->assertSee('License & Documents')
        ->assertSee('Open in Document Hub')
        ->assertSee('Upload Document')
        ->assertSee(route('admin.documents', ['practice' => $practice->id], false));
});

test('provider and practice edit pages have dedicated documents sections for view and upload', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $provider = documentsTestProvider();
    $practice = documentsTestPractice();

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderEditPage::class, ['provider' => $provider->id])
        ->assertSeeLivewire(EntityDocumentsSection::class)
        ->assertSee('Provider Documents')
        ->assertSee('Open in Document Hub')
        ->assertSee('Upload Document');

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeEditPage::class, ['practice' => $practice->id])
        ->assertSeeLivewire(EntityDocumentsSection::class)
        ->assertSee('License & Documents')
        ->assertSee('Open in Document Hub')
        ->assertSee('Upload Document');

    Livewire::actingAs($admin, 'admin')
        ->test(EntityDocumentsSection::class, ['providerId' => $provider->id])
        ->call('openUploadModal')
        ->assertSet('showModal', true)
        ->assertSee('Upload Provider Document')
        ->assertSeeHtml('position: fixed; inset: 0');

    Livewire::actingAs($admin, 'admin')
        ->test(EntityDocumentsSection::class, ['practiceId' => $practice->id])
        ->call('openUploadModal')
        ->assertSet('showModal', true)
        ->assertSee('Upload Practice Document')
        ->assertSeeHtml('position: fixed; inset: 0');
});

test('practice documents section opens the upload modal', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = documentsTestPractice();

    Livewire::actingAs($admin, 'admin')
        ->test(EntityDocumentsSection::class, ['practiceId' => $practice->id])
        ->call('openUploadModal')
        ->assertSet('showModal', true)
        ->assertSee('Upload Practice Document');
});

test('provider documents section can edit an existing document', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $provider = documentsTestProvider();
    $type = DocumentType::where('name', 'State License')->firstOrFail();
    $file = UploadedFile::fake()->create('license.pdf', 80, 'application/pdf');

    Livewire::actingAs($admin, 'admin')
        ->test(EntityDocumentsSection::class, ['providerId' => $provider->id])
        ->call('openUploadModal')
        ->set('formData.title', 'LICENSE')
        ->set('formData.document_number', 'TX-MD-100')
        ->set('formData.document_type_id', $type->id)
        ->set('formData.state', 'TX')
        ->set('formData.effective_date', '2026-01-15')
        ->set('formData.expiry_date', '2027-01-15')
        ->set('uploadFile', $file)
        ->call('saveDocument')
        ->assertHasNoErrors();

    $document = Document::where('provider_id', $provider->id)->firstOrFail();

    Livewire::actingAs($admin, 'admin')
        ->test(EntityDocumentsSection::class, ['providerId' => $provider->id])
        ->assertSeeHtml('wire:click.stop="openEditModal('.$document->id.')"')
        ->call('openEditModal', $document->id)
        ->assertSet('showModal', true)
        ->assertSet('modalMode', 'edit')
        ->assertSet('formData.title', 'LICENSE')
        ->assertSet('formData.document_number', 'TX-MD-100')
        ->assertSet('formData.effective_date', '2026-01-15')
        ->assertSet('formData.expiry_date', '2027-01-15')
        ->assertSee('Edit Provider Document')
        ->assertSee('Name')
        ->assertSee('Number')
        ->assertSee('Document Type')
        ->assertSee('Effective Date')
        ->assertSee('Expiry Date')
        ->assertSee('Leave empty to keep the current file')
        ->assertSeeHtml('position: fixed; inset: 0')
        ->assertSeeHtml('wire:click.stop="saveDocument"')
        ->set('formData.title', 'State License')
        ->set('formData.document_number', 'TX-MD-200')
        ->set('formData.effective_date', '2026-03-01')
        ->set('formData.expiry_date', '2027-03-01')
        ->call('saveDocument')
        ->assertHasNoErrors()
        ->assertSet('showModal', false);

    $document->refresh();

    expect($document->title)->toBe('State License')
        ->and($document->document_number)->toBe('TX-MD-200')
        ->and($document->effective_date?->toDateString())->toBe('2026-03-01')
        ->and($document->expiry_date?->toDateString())->toBe('2027-03-01');
});

test('practice documents section can edit name number and dates', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = documentsTestPractice();
    $type = DocumentType::where('name', 'W-9')->firstOrFail();
    $file = UploadedFile::fake()->create('practice-w9.pdf', 80, 'application/pdf');

    Livewire::actingAs($admin, 'admin')
        ->test(EntityDocumentsSection::class, ['practiceId' => $practice->id])
        ->call('openUploadModal')
        ->set('formData.title', 'Practice W-9')
        ->set('formData.document_number', 'W9-100')
        ->set('formData.document_type_id', $type->id)
        ->set('formData.effective_date', '2026-02-01')
        ->set('formData.expiry_date', '2027-02-01')
        ->set('uploadFile', $file)
        ->call('saveDocument')
        ->assertHasNoErrors();

    $document = Document::where('practice_id', $practice->id)->firstOrFail();

    Livewire::actingAs($admin, 'admin')
        ->test(EntityDocumentsSection::class, ['practiceId' => $practice->id])
        ->assertSeeHtml('wire:click.stop="openEditModal('.$document->id.')"')
        ->assertSeeHtml(route('admin.documents.download', $document, false))
        ->call('openEditModal', $document->id)
        ->assertSet('showModal', true)
        ->assertSet('modalMode', 'edit')
        ->assertSet('formData.title', 'Practice W-9')
        ->assertSet('formData.document_number', 'W9-100')
        ->assertSet('formData.effective_date', '2026-02-01')
        ->assertSet('formData.expiry_date', '2027-02-01')
        ->assertSee('Edit Practice Document')
        ->assertSee('Name')
        ->assertSee('Number')
        ->assertSee('Document Type')
        ->assertSee('Effective Date')
        ->assertSee('Expiry Date')
        ->assertSee('Leave empty to keep the current file')
        ->assertSeeHtml('position: fixed; inset: 0')
        ->assertSeeHtml('wire:click.stop="saveDocument"')
        ->set('formData.title', 'Updated Practice W-9')
        ->set('formData.document_number', 'W9-250')
        ->set('formData.effective_date', '2026-04-10')
        ->set('formData.expiry_date', '2027-04-10')
        ->call('saveDocument')
        ->assertHasNoErrors()
        ->assertSet('showModal', false);

    $document->refresh();

    expect($document->title)->toBe('Updated Practice W-9')
        ->and($document->document_number)->toBe('W9-250')
        ->and($document->effective_date?->toDateString())->toBe('2026-04-10')
        ->and($document->expiry_date?->toDateString())->toBe('2027-04-10')
        ->and($document->practice_id)->toBe($practice->id);
});

test('practice document edit keeps an existing provider link', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = documentsTestPractice();
    $provider = documentsTestProvider();
    $type = DocumentType::where('name', 'W-9')->firstOrFail();
    $file = UploadedFile::fake()->create('shared.pdf', 20, 'application/pdf');

    $document = Document::createWithFile([
        'title' => 'Shared Doc',
        'document_type_id' => $type->id,
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'document_number' => 'SH-1',
        'effective_date' => '2026-01-01',
        'expiry_date' => '2027-01-01',
    ], $file, $admin->id);

    Livewire::actingAs($admin, 'admin')
        ->test(EntityDocumentsSection::class, ['practiceId' => $practice->id])
        ->call('openEditModal', $document->id)
        ->set('formData.title', 'Shared Doc Updated')
        ->set('formData.document_number', 'SH-2')
        ->call('saveDocument')
        ->assertHasNoErrors();

    $document->refresh();

    expect($document->title)->toBe('Shared Doc Updated')
        ->and($document->document_number)->toBe('SH-2')
        ->and($document->practice_id)->toBe($practice->id)
        ->and($document->provider_id)->toBe($provider->id);
});

test('practice and provider edit pages expose document edit actions', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = documentsTestPractice();
    $provider = documentsTestProvider();
    $type = DocumentType::where('name', 'W-9')->firstOrFail();
    $file = UploadedFile::fake()->create('page-doc.pdf', 20, 'application/pdf');

    $practiceDoc = Document::createWithFile([
        'title' => 'Practice Page Doc',
        'document_type_id' => $type->id,
        'practice_id' => $practice->id,
        'document_number' => 'PP-1',
        'effective_date' => '2026-01-01',
        'expiry_date' => '2027-01-01',
    ], $file, $admin->id);

    $providerDoc = Document::createWithFile([
        'title' => 'Provider Page Doc',
        'document_type_id' => $type->id,
        'provider_id' => $provider->id,
        'document_number' => 'PR-1',
        'effective_date' => '2026-01-01',
        'expiry_date' => '2027-01-01',
    ], UploadedFile::fake()->create('provider-page.pdf', 20, 'application/pdf'), $admin->id);

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeEditPage::class, ['practice' => $practice->id])
        ->assertSeeLivewire(EntityDocumentsSection::class)
        ->assertSeeHtml('wire:click.stop="openEditModal('.$practiceDoc->id.')"');

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeDetailsPage::class, ['practice' => $practice->id, 'activeTab' => 'documents'])
        ->assertSeeLivewire(EntityDocumentsSection::class)
        ->assertSeeHtml('wire:click.stop="openEditModal('.$practiceDoc->id.')"');

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderEditPage::class, ['provider' => $provider->id])
        ->assertSeeLivewire(EntityDocumentsSection::class)
        ->assertSeeHtml('wire:click.stop="openEditModal('.$providerDoc->id.')"');

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderDetailsPage::class, ['provider' => $provider, 'activeTab' => 'documents'])
        ->assertSeeLivewire(EntityDocumentsSection::class)
        ->assertSeeHtml('wire:click.stop="openEditModal('.$providerDoc->id.')"');
});

test('document hub upload with empty practice and provider still saves and stores a number', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $type = DocumentType::where('name', 'W-9')->firstOrFail();
    $file = UploadedFile::fake()->create('hub-w9.pdf', 40, 'application/pdf');

    Livewire::actingAs($admin, 'admin')
        ->test(DocumentListPage::class)
        ->call('openUploadModal')
        ->set('formData.title', 'Hub W-9')
        ->set('formData.document_number', 'DEA-998877')
        ->set('formData.document_type_id', $type->id)
        ->set('formData.practice_id', '')
        ->set('formData.provider_id', '')
        ->set('formData.credentialing_case_id', '')
        ->set('uploadFile', $file)
        ->call('saveDocument')
        ->assertHasNoErrors();

    $document = Document::where('title', 'Hub W-9')->firstOrFail();

    expect($document->document_number)->toBe('DEA-998877')
        ->and($document->practice_id)->toBeNull()
        ->and($document->provider_id)->toBeNull();
});

test('document hub can edit fields and replace the file', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $type = DocumentType::where('name', 'W-9')->firstOrFail();
    $file = UploadedFile::fake()->create('original.pdf', 40, 'application/pdf');

    Livewire::actingAs($admin, 'admin')
        ->test(DocumentListPage::class)
        ->call('openUploadModal')
        ->set('formData.title', 'Original Hub Doc')
        ->set('formData.document_number', 'LIC-1')
        ->set('formData.document_type_id', $type->id)
        ->set('uploadFile', $file)
        ->call('saveDocument')
        ->assertHasNoErrors();

    $document = Document::where('title', 'Original Hub Doc')->firstOrFail();
    $replacement = UploadedFile::fake()->create('replaced.pdf', 50, 'application/pdf');

    Livewire::actingAs($admin, 'admin')
        ->test(DocumentListPage::class)
        ->call('openEditModal', $document->id)
        ->assertSet('showModal', true)
        ->assertSee('Edit Document')
        ->assertSee('License / Certification / DEA number')
        ->assertSeeHtml('position: fixed; inset: 0')
        ->set('formData.title', 'Updated Hub Doc')
        ->set('formData.document_number', 'LIC-2')
        ->set('uploadFile', $replacement)
        ->call('saveDocument')
        ->assertHasNoErrors();

    $document->refresh();
    $current = $document->versions()->where('is_current', true)->first();

    expect($document->title)->toBe('Updated Hub Doc')
        ->and($document->document_number)->toBe('LIC-2')
        ->and($current?->original_name)->toBe('replaced.pdf');
});

test('document hub search matches practice name, client code, provider name, and payer', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = documentsTestPractice();
    $provider = documentsTestProvider();
    $provider->practices()->syncWithoutDetaching([$practice->id]);
    $payer = Payer::create(['name' => 'Hub Payer Blue', 'is_active' => true]);
    $type = DocumentType::where('name', 'W-9')->firstOrFail();
    $file = UploadedFile::fake()->create('search.pdf', 20, 'application/pdf');

    $practiceDoc = Document::createWithFile([
        'title' => 'Practice Search Doc',
        'document_type_id' => $type->id,
        'practice_id' => $practice->id,
    ], $file, $admin->id);

    $providerDoc = Document::createWithFile([
        'title' => 'Provider Only Doc',
        'document_type_id' => $type->id,
        'provider_id' => $provider->id,
    ], UploadedFile::fake()->create('provider.pdf', 20, 'application/pdf'), $admin->id);

    $case = CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => Status::where('name', 'At Payer')->firstOrFail()->id,
        'state' => 'TX',
    ]);

    $payerDoc = Document::createWithFile([
        'title' => 'Payer Linked Doc',
        'document_type_id' => $type->id,
        'credentialing_case_id' => $case->id,
        'payer_id' => $payer->id,
    ], UploadedFile::fake()->create('payer.pdf', 20, 'application/pdf'), $admin->id);

    Livewire::actingAs($admin, 'admin')
        ->test(DocumentListPage::class)
        ->set('search', 'Docs Practice')
        ->assertSee('Practice Search Doc');

    Livewire::actingAs($admin, 'admin')
        ->test(DocumentListPage::class)
        ->set('search', 'DCP')
        ->assertSee('Practice Search Doc')
        ->assertSee('Provider Only Doc');

    Livewire::actingAs($admin, 'admin')
        ->test(DocumentListPage::class)
        ->set('search', 'Dr. Docs')
        ->assertSee('Provider Only Doc');

    Livewire::actingAs($admin, 'admin')
        ->test(DocumentListPage::class)
        ->set('search', 'Hub Payer Blue')
        ->assertSee('Payer Linked Doc')
        ->assertDontSee('Practice Search Doc');

    Livewire::actingAs($admin, 'admin')
        ->test(DocumentListPage::class)
        ->set('search', 'zzzz-no-match')
        ->assertDontSee($practiceDoc->title)
        ->assertDontSee($providerDoc->title)
        ->assertDontSee($payerDoc->title);
});

test('entity documents section blocks client-side tampering of canUpload and canVerify and enforces practice scope', function () {
    $billing = Admin::where('username', 'billing')->firstOrFail();
    $assignedPractice = documentsTestPractice();
    $unassignedPractice = Practice::create([
        'legal_name' => 'Unassigned Docs Practice',
        'client_code' => 'UDP',
        'email' => 'unassigned-docs@example.com',
        'status' => 'active',
        'user_id' => User::create([
            'name' => 'Unassigned Docs User',
            'email' => 'unassigned-docs-user@example.com',
            'password' => 'password',
        ])->id,
    ]);
    $billing->practices()->sync([$assignedPractice->id]);

    $component = Livewire::actingAs($billing, 'admin')
        ->test(EntityDocumentsSection::class, ['practiceId' => $assignedPractice->id])
        ->assertSet('canUpload', false)
        ->assertSet('canVerify', false);

    expect(fn () => $component->set('canUpload', true))
        ->toThrow(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

    expect(fn () => $component->set('canVerify', true))
        ->toThrow(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

    $component->call('openUploadModal')
        ->assertForbidden();

    Livewire::actingAs($billing, 'admin')
        ->test(EntityDocumentsSection::class, ['practiceId' => $unassignedPractice->id])
        ->assertForbidden();
});
