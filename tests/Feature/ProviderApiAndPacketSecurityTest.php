<?php

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\CaseType;
use App\Models\CredentialingCase;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\FormTemplate;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\Priority;
use App\Models\ProviderDetails;
use App\Models\Specialty;
use App\Models\Status;
use App\Models\User;
use App\Services\PacketBuilderService;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(AdminPermissionSeeder::class);
    $this->seed(AdminSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(MasterDataSeeder::class);
});

test('PacketBuilderService::buildHtml escapes HTML special characters in provider, case, practice, payer, and document fields', function () {
    $xss = '<script>alert(1)</script>';

    $practiceUser = User::create([
        'name' => 'Practice Owner',
        'email' => 'pkt-practice@example.com',
        'password' => 'password123',
    ]);

    $practice = Practice::create([
        'user_id' => $practiceUser->id,
        'legal_name' => "Clinic {$xss}",
        'client_code' => 'PKT001',
        'ein_tin' => "99-{$xss}",
        'email' => 'pkt-practice@example.com',
        'status' => 'active',
    ]);

    $providerUser = User::create([
        'name' => "Dr. Evil {$xss}",
        'email' => 'pkt-provider@example.com',
        'password' => 'password123',
    ]);

    $specialty = Specialty::create([
        'name' => "Cardiology {$xss}",
        'code' => 'CARD01',
    ]);

    $provider = ProviderDetails::create([
        'user_id' => $providerUser->id,
        'specialty_id' => $specialty->id,
        'npi' => '1234567890',
        'status' => 'active',
    ]);

    $payer = Payer::create([
        'name' => "Payer {$xss}",
        'slug' => 'payer-xss',
        'payer_type' => 'Commercial',
    ]);

    $caseType = CaseType::create([
        'name' => "Initial {$xss}",
        'slug' => 'initial-xss',
    ]);

    $priority = Priority::create([
        'name' => "High {$xss}",
        'slug' => 'high-xss',
        'sort_order' => 1,
    ]);

    $status = Status::create([
        'name' => "In Progress {$xss}",
        'slug' => 'in-progress-xss',
        'dashboard_category' => 'provider',
        'sort_order' => 1,
    ]);

    $case = CredentialingCase::create([
        'case_number' => 'PKT001-2026-0001',
        'provider_id' => $provider->id,
        'practice_id' => $practice->id,
        'payer_id' => $payer->id,
        'case_type_id' => $caseType->id,
        'priority_id' => $priority->id,
        'status_id' => $status->id,
        'notes' => "Case notes {$xss}",
        'state' => 'TX',
    ]);

    $docType = DocumentType::create([
        'name' => "Medical License {$xss}",
        'slug' => 'med-lic-xss',
    ]);

    Document::create([
        'credentialing_case_id' => $case->id,
        'provider_id' => $provider->id,
        'practice_id' => $practice->id,
        'document_type_id' => $docType->id,
        'title' => "License File {$xss}",
        'document_number' => "LIC-{$xss}",
        'status' => 'active',
        'verification_status' => "verified {$xss}",
    ]);

    $template = FormTemplate::create([
        'name' => 'Standard Packet',
        'template_key' => 'std_packet',
        'version' => '1.0',
        'field_mapping' => [
            "Notes <img src=x onerror=alert(1)>" => 'notes',
        ],
        'is_active' => true,
    ]);

    $html = app(PacketBuilderService::class)->buildHtml($case, $template);

    expect($html)
        ->not->toContain('<script>alert(1)</script>')
        ->not->toContain('<img src=x onerror=alert(1)>')
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
});

test('POST /api/v1/auth/token issues a Sanctum token for valid credentials and rejects invalid credentials', function () {
    $user = User::create([
        'name' => 'API Provider User',
        'email' => 'api-provider@example.com',
        'password' => 'secret-password-123',
    ]);

    ProviderDetails::create([
        'user_id' => $user->id,
        'npi' => '1122334455',
        'status' => 'active',
    ]);

    // Valid credentials
    $validResponse = $this->postJson('/api/v1/auth/token', [
        'email' => 'api-provider@example.com',
        'password' => 'secret-password-123',
        'token_name' => 'integration-suite',
    ]);

    $validResponse->assertOk()
        ->assertJsonStructure(['status', 'token'])
        ->assertJsonPath('status', 'ok');

    expect($validResponse->json('token'))->toBeString()->not->toBeEmpty();

    // Invalid credentials
    $invalidResponse = $this->postJson('/api/v1/auth/token', [
        'email' => 'api-provider@example.com',
        'password' => 'wrong-password',
    ]);

    $invalidResponse->assertStatus(401)
        ->assertJsonPath('status', 'error');
});

test('ProviderApiController::cases and documents return 404 JSON instead of 500 when provider does not exist', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/providers/999999/cases')
        ->assertNotFound()
        ->assertJson([
            'status' => 'error',
            'message' => 'Provider not found.',
        ]);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/providers/999999/documents')
        ->assertNotFound()
        ->assertJson([
            'status' => 'error',
            'message' => 'Provider not found.',
        ]);

    // Also verify user without providerDetails calling /api/v1/provider/cases gets 404 instead of 500
    $userWithoutProvider = User::create([
        'name' => 'Unlinked User',
        'email' => 'unlinked-user@example.com',
        'password' => 'password123',
    ]);

    $this->actingAs($userWithoutProvider, 'sanctum')
        ->getJson('/api/v1/provider/cases')
        ->assertNotFound()
        ->assertJson([
            'status' => 'error',
            'message' => 'Provider not found.',
        ]);
});

test('ProviderApiController::cases and documents enforce scope and authorization for portal users and practice-scoped admins', function () {
    $practiceA = Practice::create([
        'user_id' => User::create(['name' => 'PA', 'email' => 'pa-api@example.com', 'password' => 'password'])->id,
        'legal_name' => 'Practice A',
        'client_code' => 'PRACA1',
        'email' => 'pa-api@example.com',
        'status' => 'active',
    ]);

    $practiceB = Practice::create([
        'user_id' => User::create(['name' => 'PB', 'email' => 'pb-api@example.com', 'password' => 'password'])->id,
        'legal_name' => 'Practice B',
        'client_code' => 'PRACB1',
        'email' => 'pb-api@example.com',
        'status' => 'active',
    ]);

    $userA = User::create([
        'name' => 'Dr. A',
        'email' => 'dra@example.com',
        'password' => 'password',
    ]);
    $providerA = ProviderDetails::create([
        'user_id' => $userA->id,
        'npi' => '1000000001',
        'status' => 'active',
    ]);
    $providerA->practices()->sync([$practiceA->id => ['primary_flag' => true]]);

    $userB = User::create([
        'name' => 'Dr. B',
        'email' => 'drb@example.com',
        'password' => 'password',
    ]);
    $providerB = ProviderDetails::create([
        'user_id' => $userB->id,
        'npi' => '1000000002',
        'status' => 'active',
    ]);
    $providerB->practices()->sync([$practiceB->id => ['primary_flag' => true]]);

    // Portal user A can access provider A but is forbidden from provider B
    $this->actingAs($userA, 'sanctum')
        ->getJson("/api/v1/providers/{$providerA->id}/cases")
        ->assertOk();

    $this->actingAs($userA, 'sanctum')
        ->getJson("/api/v1/providers/{$providerB->id}/cases")
        ->assertForbidden();

    $this->actingAs($userA, 'sanctum')
        ->getJson("/api/v1/providers/{$providerB->id}/documents")
        ->assertForbidden();

    // Practice-scoped admin assigned to Practice A can access provider A but is forbidden from provider B
    $scopedAdmin = Admin::create([
        'name' => 'Scoped Admin',
        'username' => 'scoped_api_admin',
        'email' => 'scoped-api@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);
    $scopedAdmin->assignRole(AdminRole::CredentialingExecutive->value);
    $scopedAdmin->practices()->sync([$practiceA->id]);

    $this->actingAs($scopedAdmin, 'sanctum')
        ->getJson("/api/v1/providers/{$providerA->id}/cases")
        ->assertOk();

    $this->actingAs($scopedAdmin, 'sanctum')
        ->getJson("/api/v1/providers/{$providerB->id}/cases")
        ->assertForbidden();

    $this->actingAs($scopedAdmin, 'sanctum')
        ->getJson("/api/v1/providers/{$providerB->id}/documents")
        ->assertForbidden();
});
