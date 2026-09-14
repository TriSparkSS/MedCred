<?php

use App\Livewire\Admin\Documents\DocumentListPage;
use App\Livewire\Admin\Documents\EntityDocumentsSection;
use App\Models\Admin;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Practice;
use App\Models\ProviderDetails;
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
        'status' => 'approved',
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
        ->set('formData.document_type_id', $type->id)
        ->set('uploadFile', $file)
        ->call('saveDocument')
        ->assertHasNoErrors();

    $document = Document::where('practice_id', $practice->id)->firstOrFail();
    expect($document->title)->toBe('Practice W-9')
        ->and($document->provider_id)->toBeNull();

    Livewire::actingAs($admin, 'admin')
        ->test(DocumentListPage::class)
        ->set('filterPractice', (string) $practice->id)
        ->assertSee('Practice W-9');
});
