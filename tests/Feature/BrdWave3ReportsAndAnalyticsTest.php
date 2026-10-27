<?php

use App\Livewire\Admin\Reports\PracticeCredentialingReportPage;
use App\Livewire\Admin\Reports\ReportListPage;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\CredentialingCase;
use App\Models\DelayOwner;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\Specialty;
use App\Models\Status;
use App\Models\User;
use App\Services\ReportExportService;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

function wave3Practice(string $name, string $code): Practice
{
    $user = User::create([
        'name' => $name.' Owner',
        'email' => strtolower($code).'-'.uniqid().'@example.com',
        'password' => 'password123',
    ]);

    return Practice::create([
        'user_id' => $user->id,
        'legal_name' => $name,
        'client_code' => $code,
        'email' => strtolower($code).'@example.com',
        'status' => 'active',
    ]);
}

function wave3Provider(string $name, string $npi, Practice $practice, ?Specialty $specialty = null): ProviderDetails
{
    $user = User::create([
        'name' => $name,
        'email' => strtolower(str_replace(' ', '.', $name)).'-'.uniqid().'@example.com',
        'password' => 'password123',
    ]);

    $provider = ProviderDetails::create([
        'user_id' => $user->id,
        'npi' => $npi,
        'specialty_id' => $specialty?->id,
        'status' => 'active',
    ]);

    $provider->practices()->attach($practice->id, ['primary_flag' => true]);

    return $provider;
}

test('BRD #13: delayManagementBreakdown computes counts, share percentages, and average aging days across 4 dimensions', function () {
    $practice = wave3Practice('Wave3 Delay Practice', 'WDP');
    $providerA = wave3Provider('Dr. Delay Alpha', '1112223334', $practice);
    $providerB = wave3Provider('Dr. Delay Beta', '2223334445', $practice);
    $payerA = Payer::create(['name' => 'Aetna Health', 'is_active' => true]);
    $payerB = Payer::create(['name' => 'UnitedHealthcare', 'is_active' => true]);

    $execA = Admin::create([
        'name' => 'Executive Alice',
        'username' => 'exec_alice',
        'email' => 'alice@example.com',
        'password' => 'password123',
        'status' => 1,
    ]);
    $execB = Admin::create([
        'name' => 'Executive Bob',
        'username' => 'exec_bob',
        'email' => 'bob@example.com',
        'password' => 'password123',
        'status' => 1,
    ]);

    $atPayerStatus = Status::where('name', 'At Payer')->firstOrFail();
    $docsReqStatus = Status::where('name', 'Documents Requested from Provider')->firstOrFail();
    $internalStatus = Status::where('name', 'Pending Internal Review')->firstOrFail();

    $payerOwner = DelayOwner::where('name', 'Payer')->firstOrFail();
    $providerOwner = DelayOwner::where('name', 'Provider/Practice')->firstOrFail();
    $revantageOwner = DelayOwner::where('name', 'Revantage Team')->firstOrFail();

    // 2 cases delayed by Payer (aging 20 and 40 days -> avg 30)
    CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $providerA->id,
        'payer_id' => $payerA->id,
        'status_id' => $atPayerStatus->id,
        'delay_owner_id' => $payerOwner->id,
        'assigned_admin_id' => $execA->id,
        'state' => 'TX',
        'intake_date' => now()->subDays(20)->toDateString(),
    ]);
    CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $providerA->id,
        'payer_id' => $payerA->id,
        'status_id' => $atPayerStatus->id,
        'delay_owner_id' => $payerOwner->id,
        'assigned_admin_id' => $execA->id,
        'state' => 'TX',
        'intake_date' => now()->subDays(40)->toDateString(),
    ]);

    // 1 case delayed by Provider/Practice (aging 10 days)
    CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $providerB->id,
        'payer_id' => $payerB->id,
        'status_id' => $docsReqStatus->id,
        'delay_owner_id' => $providerOwner->id,
        'assigned_admin_id' => $execB->id,
        'state' => 'CA',
        'intake_date' => now()->subDays(10)->toDateString(),
    ]);

    // 1 case delayed by Revantage Team (aging 14 days)
    CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $providerB->id,
        'payer_id' => $payerB->id,
        'status_id' => $internalStatus->id,
        'delay_owner_id' => $revantageOwner->id,
        'assigned_admin_id' => $execB->id,
        'state' => 'FL',
        'intake_date' => now()->subDays(14)->toDateString(),
    ]);

    $service = app(ReportExportService::class);
    $breakdown = $service->delayManagementBreakdown();

    expect($breakdown['total_active'])->toBe(4)
        ->and($breakdown)->toHaveKeys(['total_active', 'by_owner', 'by_team', 'by_provider', 'by_payer']);

    $byOwner = collect($breakdown['by_owner'])->keyBy('label');
    expect($byOwner['Payer']['count'])->toBe(2)
        ->and($byOwner['Payer']['percent'])->toBe(50.0)
        ->and($byOwner['Payer']['avg_aging_days'])->toBe(30)
        ->and($byOwner['Provider/Practice']['count'])->toBe(1)
        ->and($byOwner['Provider/Practice']['percent'])->toBe(25.0)
        ->and($byOwner['Revantage Team']['count'])->toBe(1)
        ->and($byOwner['Revantage Team']['percent'])->toBe(25.0);

    $byTeam = collect($breakdown['by_team'])->keyBy('label');
    expect($byTeam['Executive Alice']['count'])->toBe(2)
        ->and($byTeam['Executive Alice']['percent'])->toBe(50.0)
        ->and($byTeam['Executive Bob']['count'])->toBe(2)
        ->and($byTeam['Executive Bob']['percent'])->toBe(50.0);

    $byProvider = collect($breakdown['by_provider'])->keyBy('label');
    expect($byProvider['Dr. Delay Alpha']['count'])->toBe(2)
        ->and($byProvider['Dr. Delay Beta']['count'])->toBe(2);

    $byPayer = collect($breakdown['by_payer'])->keyBy('label');
    expect($byPayer['Aetna Health']['count'])->toBe(2)
        ->and($byPayer['UnitedHealthcare']['count'])->toBe(2);
});

test('BRD #14: approvalDenialSummary and new report definitions compute approval rates, denials, and panel-closed cases', function () {
    $practice = wave3Practice('Wave3 Analytics Practice', 'WAP');
    $specialty = Specialty::create(['name' => 'Cardiology', 'code' => 'CARD', 'is_active' => true]);
    $provider = wave3Provider('Dr. Heart', '5556667778', $practice, $specialty);
    $payer = Payer::create(['name' => 'BlueCross', 'is_active' => true]);

    $approvedStatus = Status::where('name', 'Approved')->firstOrFail();
    $deniedStatus = Status::where('name', 'Rejected / Denied')->firstOrFail();
    $panelClosedStatus = Status::where('name', 'Panel Closed')->firstOrFail();
    $atPayerStatus = Status::where('name', 'At Payer')->firstOrFail();

    // 2 Approved, 1 Denied, 1 Panel Closed, 1 At Payer = 5 total (40% approval rate)
    CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $approvedStatus->id,
        'state' => 'NY',
        'submission_date' => now()->subDays(30)->toDateString(),
        'effective_date' => now()->subDays(10)->toDateString(),
    ]);
    CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $approvedStatus->id,
        'state' => 'NY',
        'submission_date' => now()->subDays(25)->toDateString(),
        'effective_date' => now()->subDays(5)->toDateString(),
    ]);
    $deniedCase = CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $deniedStatus->id,
        'state' => 'NY',
        'notes' => 'Missing board certification',
    ]);
    $panelClosedCase = CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $panelClosedStatus->id,
        'state' => 'NJ',
        'notes' => 'Network panel closed in county',
    ]);
    CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $atPayerStatus->id,
        'state' => 'NY',
    ]);

    $definitions = ReportExportService::definitions();
    expect($definitions)->toHaveCount(12)
        ->and($definitions)->toHaveKey('approval_denial_trends')
        ->and($definitions)->toHaveKey('panel_closed_summary');

    $service = app(ReportExportService::class);
    $summary = $service->approvalDenialSummary();

    expect($summary['total_cases'])->toBe(5)
        ->and($summary['approved_count'])->toBe(2)
        ->and($summary['Approval_rate_percent'])->toBe(40.0)
        ->and($summary['approval_rate_percent'])->toBe(40.0)
        ->and($summary['denied_count'])->toBe(1)
        ->and($summary['panel_closed_count'])->toBe(1)
        ->and($summary['by_payer'])->toHaveCount(1)
        ->and($summary['by_payer'][0]['payer'])->toBe('BlueCross');

    $panelData = $service->rowsFor('panel_closed_summary');
    expect($panelData['rows'])->toHaveCount(2);
    $panelCsv = $service->csvContents('panel_closed_summary');
    expect($panelCsv)->toContain($deniedCase->case_number)
        ->and($panelCsv)->toContain($panelClosedCase->case_number)
        ->and($panelCsv)->toContain('Cardiology')
        ->and($panelCsv)->toContain('Panel Closed');
});

test('BRD #15: rowsFor supports all 12 report types and ReportListPage renders interactive screen and delay tabs', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = wave3Practice('Interactive Screen Practice', 'ISP');
    $provider = wave3Provider('Dr. Screen Viewer', '9998887776', $practice);
    $payer = Payer::create(['name' => 'Cigna', 'is_active' => true]);
    $status = Status::where('name', 'At Payer')->firstOrFail();

    $case = CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
        'state' => 'MD',
    ]);

    $this->actingAs($admin, 'admin');
    $service = app(ReportExportService::class);

    foreach (array_keys(ReportExportService::definitions()) as $type) {
        $data = $service->rowsFor($type);
        expect($data)->toHaveKeys(['filename', 'headers', 'rows'])
            ->and($data['headers'])->toBeArray()->not->toBeEmpty()
            ->and($data['rows'])->toBeArray();
    }

    Livewire::actingAs($admin, 'admin')
        ->test(ReportListPage::class)
        ->assertSee('View Screen')
        ->assertSee('Management Delay Breakdown')
        ->assertSee('Approval, Denial & Panel-Closed Summary')
        ->call('viewReport', 'open_applications')
        ->assertSet('activeReport', 'open_applications')
        ->assertSee($case->case_number)
        ->assertSee('Dr. Screen Viewer')
        ->call('setDelayDimension', 'by_payer')
        ->assertSet('delayDimension', 'by_payer')
        ->call('closeReport')
        ->assertSet('activeReport', '');
});

test('BRD #8: exporting CSV or Practice Credentialing XLSX records report.exported audit log', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = wave3Practice('Audit Export Practice', 'AEP');
    $provider = wave3Provider('Dr. Audit', '4445556667', $practice);
    $payer = Payer::create(['name' => 'Humana', 'is_active' => true]);
    $status = Status::where('name', 'Approved')->firstOrFail();

    CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
        'state' => 'VA',
    ]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.reports.export', 'approval_denial_trends'))
        ->assertOk();

    $csvLog = AuditLog::where('action', 'report.exported')
        ->where('admin_id', $admin->id)
        ->latest('id')
        ->first();

    expect($csvLog)->not->toBeNull()
        ->and($csvLog->metadata['report_type'] ?? null)->toBe('approval_denial_trends');

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeCredentialingReportPage::class)
        ->call('downloadExcel')
        ->assertFileDownloaded('total_credentialing_report_by_practice_'.now()->format('Ymd').'.xlsx');

    $xlsxLog = AuditLog::where('action', 'report.exported')
        ->where('admin_id', $admin->id)
        ->where('id', '>', $csvLog->id)
        ->first();

    expect($xlsxLog)->not->toBeNull()
        ->and($xlsxLog->metadata['report_type'] ?? null)->toBe('practice_credentialing_xlsx');
});
