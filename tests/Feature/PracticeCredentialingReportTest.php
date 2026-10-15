<?php

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\CaseActivity;
use App\Models\CredentialingCase;
use App\Models\EmailMessage;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\Status;
use App\Models\User;
use App\Services\ReportExportService;
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

function reportTestPractice(string $name, string $code): Practice
{
    $user = User::create([
        'name' => $name.' User',
        'email' => strtolower($code).'-user@example.com',
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

function reportTestProvider(string $name, string $npi, Practice $practice): ProviderDetails
{
    $user = User::create([
        'name' => $name,
        'email' => strtolower(str_replace(' ', '.', $name)).'-'.uniqid().'@example.com',
        'password' => 'password123',
    ]);

    $provider = ProviderDetails::create([
        'user_id' => $user->id,
        'npi' => $npi,
        'status' => 'active',
    ]);

    $provider->practices()->attach($practice->id, ['primary_flag' => true]);

    return $provider;
}

test('practiceCredentialingDataset populates latest_comment and last_activity across multiple cases using latestActivity', function () {
    $practice = reportTestPractice('Multi Case Practice', 'MCP');
    $provider1 = reportTestProvider('Dr. One', '1010101010', $practice);
    $provider2 = reportTestProvider('Dr. Two', '2020202020', $practice);
    $payer = Payer::create(['name' => 'Aetna', 'is_active' => true]);
    $status = Status::where('name', 'At Payer')->firstOrFail();

    $case1 = CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider1->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
        'state' => 'TX',
    ]);
    CaseActivity::create([
        'credentialing_case_id' => $case1->id,
        'activity_type' => 'note',
        'summary' => 'Case 1 older note',
    ]);
    CaseActivity::create([
        'credentialing_case_id' => $case1->id,
        'activity_type' => 'note',
        'summary' => 'Case 1 latest comment',
    ]);

    $case2 = CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider2->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
        'state' => 'CA',
    ]);
    CaseActivity::create([
        'credentialing_case_id' => $case2->id,
        'activity_type' => 'note',
        'summary' => 'Case 2 older note',
    ]);
    CaseActivity::create([
        'credentialing_case_id' => $case2->id,
        'activity_type' => 'note',
        'summary' => 'Case 2 latest comment',
    ]);

    $service = app(ReportExportService::class);
    $dataset = collect($service->practiceCredentialingDataset())->keyBy('case_id');

    expect($dataset)->toHaveCount(2)
        ->and($dataset[$case1->id]['latest_comment'])->toBe('Case 1 latest comment')
        ->and($dataset[$case1->id]['last_activity'])->toBe('Case 1 latest comment')
        ->and($dataset[$case2->id]['latest_comment'])->toBe('Case 2 latest comment')
        ->and($dataset[$case2->id]['last_activity'])->toBe('Case 2 latest comment');
});

test('toCsv and csvContents escape CSV formula injection prefixes while preserving numeric values', function () {
    $service = app(ReportExportService::class);

    $csv = $service->toCsv(
        ['Name', 'Formula', 'Plus', 'MinusText', 'NegativeNum', 'AtHandle'],
        [
            ['Dr. Safe', '=SUM(A1:A2)', '+cmd|\'/C calc\'!A0', '-1+1', '-42.5', '@SUM(1,2)'],
        ]
    );

    expect($csv)->toContain("'=SUM(A1:A2)")
        ->and($csv)->toContain("'+cmd|'/C calc'!A0")
        ->and($csv)->toContain("'-1+1")
        ->and($csv)->toContain('-42.5')
        ->and($csv)->not->toContain("'-42.5")
        ->and($csv)->toContain("'@SUM(1,2)");
});

test('ReportExportService exports email_operations and delay_ownership and enforces AdminScopeService', function () {
    $practiceA = reportTestPractice('Practice Alpha', 'PAL');
    $practiceB = reportTestPractice('Practice Beta', 'PBE');
    $providerA = reportTestProvider('Dr. Alpha', '3030303030', $practiceA);
    $providerB = reportTestProvider('Dr. Beta', '4040404040', $practiceB);
    $payer = Payer::create(['name' => 'Cigna', 'is_active' => true]);
    $status = Status::where('name', 'At Payer')->firstOrFail();

    $caseA = CredentialingCase::create([
        'practice_id' => $practiceA->id,
        'provider_id' => $providerA->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
        'state' => 'TX',
    ]);
    $caseB = CredentialingCase::create([
        'practice_id' => $practiceB->id,
        'provider_id' => $providerB->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
        'state' => 'FL',
    ]);

    EmailMessage::create([
        'credentialing_case_id' => $caseA->id,
        'provider_id' => $providerA->id,
        'direction' => 'inbound',
        'from_address' => 'alpha@example.com',
        'to_address' => 'crm@example.com',
        'subject' => 'Alpha Case Email',
        'body' => 'Body A',
        'status' => 'received',
    ]);
    EmailMessage::create([
        'credentialing_case_id' => $caseB->id,
        'provider_id' => $providerB->id,
        'direction' => 'inbound',
        'from_address' => 'beta@example.com',
        'to_address' => 'crm@example.com',
        'subject' => 'Beta Case Email',
        'body' => 'Body B',
        'status' => 'received',
    ]);

    $scopedAdmin = Admin::create([
        'name' => 'Scoped Executive',
        'username' => 'scoped_exec_report',
        'email' => 'scoped-exec-report@example.com',
        'password' => 'password123',
        'status' => 1,
    ]);
    $scopedAdmin->assignRole(AdminRole::CredentialingExecutive->value);
    $scopedAdmin->practices()->sync([$practiceA->id]);

    $this->actingAs($scopedAdmin, 'admin');

    $service = app(ReportExportService::class);

    ob_start();
    $service->export('email_operations')->sendContent();
    $emailCsv = ob_get_clean();

    expect($emailCsv)->toContain('Alpha Case Email')
        ->and($emailCsv)->not->toContain('Beta Case Email');

    ob_start();
    $service->export('delay_ownership')->sendContent();
    $delayCsv = ob_get_clean();

    expect($delayCsv)->toContain($caseA->case_number)
        ->and($delayCsv)->not->toContain($caseB->case_number);
});
