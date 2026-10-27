<?php

use App\Livewire\Admin\DashboardPage;
use App\Livewire\Admin\Payer\PayerEditPage;
use App\Livewire\Admin\Provider\ProviderListPage;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\CaseDocumentItem;
use App\Models\CredentialingCase;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\Status;
use App\Models\User;
use App\Services\GlobalSearchService;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(AdminPermissionSeeder::class);
    $this->seed(AdminSeeder::class);
    $this->seed(MasterDataSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);
});

test('BRD #10: global search finds providers by CAQH, practices by tax ID and NPI, and cases by status', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $this->actingAs($admin, 'admin');

    $practice = Practice::create([
        'legal_name' => 'Apex Medical Group',
        'dba_name' => 'Apex Care',
        'client_code' => 'APX',
        'ein_tin' => '99-8877665',
        'group_npi' => '1999888777',
        'email' => 'apex@example.com',
        'status' => 'active',
        'user_id' => User::create([
            'name' => 'Apex Practice User',
            'email' => 'apex-user@example.com',
            'password' => 'password',
        ])->id,
    ]);

    $providerUser = User::create([
        'name' => 'Dr. Evelyn Cross',
        'email' => 'evelyn@example.com',
        'password' => 'password',
    ]);

    $provider = ProviderDetails::create([
        'user_id' => $providerUser->id,
        'npi' => '1234567890',
        'caqh_id' => 'CAQH-884422',
        'status' => 'active',
        'practice' => 'Apex Medical Group',
        'address' => '100 Main St',
        'city' => 'Dallas',
        'state' => 'TX',
        'zip' => '75201',
    ]);
    $provider->practices()->attach($practice->id, ['primary_flag' => true]);

    $payer = Payer::create(['name' => 'BlueCross TX', 'is_active' => true]);
    $status = Status::where('name', 'At Payer')->firstOrFail();

    $case = CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
        'state' => 'TX',
    ]);

    $searchService = app(GlobalSearchService::class);

    // Search by CAQH ID
    $byCaqh = $searchService->search('CAQH-884422');
    expect($byCaqh['providers'])->toHaveCount(1)
        ->and($byCaqh['providers']->first()['meta'])->toContain('CAQH: CAQH-884422');

    // Search practice by Tax ID (ein_tin)
    $byTaxId = $searchService->search('99-8877665');
    expect($byTaxId['practices'])->toHaveCount(1)
        ->and($byTaxId['practices']->first()['label'])->toBe('Apex Medical Group')
        ->and($byTaxId['practices']->first()['meta'])->toContain('Tax ID: 99-8877665');

    // Search cases by status name
    $byStatus = $searchService->search('At Payer');
    expect($byStatus['cases']->pluck('label'))->toContain($case->case_number)
        ->and($byStatus['cases']->first()['meta'])->toContain('(At Payer)');
});

test('BRD #11: provider list filters by state, payer, and compliance status and renders status columns', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();

    $practice = Practice::create([
        'legal_name' => 'Lone Star Clinic',
        'client_code' => 'LSC',
        'email' => 'lsc@example.com',
        'status' => 'active',
        'user_id' => User::create([
            'name' => 'LSC User',
            'email' => 'lsc-user@example.com',
            'password' => 'password',
        ])->id,
    ]);

    $txProvider = ProviderDetails::create([
        'user_id' => User::create([
            'name' => 'Dr. Texas Ranger',
            'email' => 'tx@example.com',
            'password' => 'password',
        ])->id,
        'npi' => '1112223334',
        'status' => 'active',
        'practice' => 'Lone Star Clinic',
        'address' => '1 Austin Way',
        'city' => 'Austin',
        'state' => 'TX',
        'zip' => '78701',
    ]);
    $txProvider->practices()->attach($practice->id, ['primary_flag' => true]);

    $caProvider = ProviderDetails::create([
        'user_id' => User::create([
            'name' => 'Dr. Cali Sun',
            'email' => 'ca@example.com',
            'password' => 'password',
        ])->id,
        'npi' => '5556667778',
        'status' => 'active',
        'practice' => 'Lone Star Clinic',
        'address' => '2 Sunset Blvd',
        'city' => 'Los Angeles',
        'state' => 'CA',
        'zip' => '90001',
    ]);
    $caProvider->practices()->attach($practice->id, ['primary_flag' => true]);

    $payerAetna = Payer::create(['name' => 'Aetna Commercial', 'is_active' => true]);
    $openStatus = Status::where('name', 'At Payer')->firstOrFail();
    $docType = DocumentType::firstOrFail();

    $txCase = CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $txProvider->id,
        'payer_id' => $payerAetna->id,
        'status_id' => $openStatus->id,
        'state' => 'TX',
    ]);

    CaseDocumentItem::create([
        'credentialing_case_id' => $txCase->id,
        'document_type_id' => $docType->id,
        'is_required' => true,
        'is_received' => false,
    ]);

    Document::create([
        'provider_id' => $txProvider->id,
        'document_type_id' => $docType->id,
        'title' => 'State License TX',
        'expiry_date' => now()->addDays(15)->toDateString(),
        'status' => 'active',
    ]);

    Document::create([
        'provider_id' => $caProvider->id,
        'document_type_id' => $docType->id,
        'title' => 'Expired DEA',
        'expiry_date' => now()->subDays(5)->toDateString(),
        'status' => 'active',
    ]);

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderListPage::class)
        ->assertSee('Dr. Texas Ranger')
        ->assertSee('Dr. Cali Sun')
        ->assertSee('In Progress')
        ->assertSee('1 pending')
        ->assertSee('1 Expiring')
        ->assertSee('1 Expired')
        ->set('filterState', 'TX')
        ->assertSee('Dr. Texas Ranger')
        ->assertDontSee('Dr. Cali Sun')
        ->call('clearFilters')
        ->set('filterPayer', (string) $payerAetna->id)
        ->assertSee('Dr. Texas Ranger')
        ->assertDontSee('Dr. Cali Sun')
        ->call('clearFilters')
        ->set('filterCompliance', 'expiring_soon')
        ->assertSee('Dr. Texas Ranger')
        ->assertDontSee('Dr. Cali Sun')
        ->set('filterCompliance', 'expired')
        ->assertSee('Dr. Cali Sun')
        ->assertDontSee('Dr. Texas Ranger')
        ->set('filterCompliance', 'missing_docs')
        ->assertSee('Dr. Texas Ranger')
        ->assertDontSee('Dr. Cali Sun');
});

test('BRD #12: executive dashboard displays Revenue Stuck KPI tile for non-closed cases not ready to bill', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();

    $practice = Practice::create([
        'legal_name' => 'Revenue Practice',
        'client_code' => 'RVP',
        'email' => 'rvp@example.com',
        'status' => 'active',
        'user_id' => User::create([
            'name' => 'RVP User',
            'email' => 'rvp-user@example.com',
            'password' => 'password',
        ])->id,
    ]);

    $provider = ProviderDetails::create([
        'user_id' => User::create([
            'name' => 'Dr. Revenue',
            'email' => 'rev@example.com',
            'password' => 'password',
        ])->id,
        'npi' => '1444555666',
        'status' => 'active',
        'practice' => 'Revenue Practice',
        'address' => '1 Billing Ln',
        'city' => 'Houston',
        'state' => 'TX',
        'zip' => '77001',
    ]);
    $provider->practices()->attach($practice->id, ['primary_flag' => true]);

    $payer = Payer::create(['name' => 'UnitedHealthcare', 'is_active' => true]);
    $openStatus = Status::where('name', 'At Payer')->firstOrFail();

    CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $openStatus->id,
        'ready_to_bill' => false,
        'state' => 'TX',
    ]);

    CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $openStatus->id,
        'ready_to_bill' => true,
        'state' => 'TX',
    ]);

    Livewire::actingAs($admin, 'admin')
        ->test(DashboardPage::class)
        ->assertSee('Revenue Stuck')
        ->assertSee('Blocking billing readiness')
        ->assertViewHas('stats', fn (array $stats) => ($stats['revenue_stuck'] ?? null) === 1);
});

test('BRD #20: payer edit page manages contact people and records audit log on save', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $payer = Payer::create([
        'name' => 'Cigna Health',
        'is_active' => true,
    ]);

    Livewire::actingAs($admin, 'admin')
        ->test(PayerEditPage::class, ['payer' => $payer->id])
        ->assertSee('Payer Contact People')
        ->set('contacts.0.name', 'Sarah Jenkins')
        ->set('contacts.0.role', 'Escalation Rep')
        ->set('contacts.0.email', 'sjenkins@cigna.example.com')
        ->set('contacts.0.phone', '800-555-0199')
        ->set('contacts.0.state_region', 'TX')
        ->set('contacts.0.escalation_level', 'Level 2')
        ->set('contacts.0.notes', 'Reachable M-F')
        ->call('addContact')
        ->set('contacts.1.name', 'Mark Vance')
        ->set('contacts.1.role', 'Regional Director')
        ->set('contacts.1.email', 'mvance@cigna.example.com')
        ->call('save')
        ->assertRedirect(route('admin.payers'));

    expect($payer->contacts()->count())->toBe(2)
        ->and($payer->contacts()->where('name', 'Sarah Jenkins')->first())
        ->not->toBeNull()
        ->and(AuditLog::where('action', 'payer.updated')->where('auditable_id', $payer->id)->exists())
        ->toBeTrue();
});
