<?php

use App\Livewire\Admin\Imports\BulkImportPage;
use App\Models\Admin;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\User;
use App\Services\SpreadsheetImportService;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(AdminPermissionSeeder::class);
    $this->seed(AdminSeeder::class);
    $this->seed(RoleSeeder::class);
});

function spreadsheetImportFile(string $filename, array $headers, array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray(array_merge([$headers], $rows));
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.$filename;
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, $filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

test('practice excel import creates two practices and isolates duplicate-row failures', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $service = app(SpreadsheetImportService::class);

    $headers = SpreadsheetImportService::PRACTICE_COLUMNS;
    $row = fn (string $name, string $code, string $email) => array_map(
        fn (string $col) => ([
            'legal_name' => $name,
            'client_code' => $code,
            'email' => $email,
            'status' => 'pending',
            'address1' => '100 Main St',
            'city' => 'Austin',
            'state' => 'TX',
            'zip_code' => '78701',
            'country' => 'United States',
        ])[$col] ?? '',
        $headers
    );

    $file = spreadsheetImportFile('practices.xlsx', $headers, [
        $row('Alpha Medical Group', 'AMG', 'alpha-practice@example.com'),
        $row('Beta Medical Group', 'BMG', 'beta-practice@example.com'),
        $row('Alpha Duplicate', 'AMG', 'alpha-practice@example.com'),
    ]);

    $batch = $service->importPractices($file, $admin->id);

    expect($batch->success_rows)->toBe(2)
        ->and($batch->failed_rows)->toBe(1)
        ->and($batch->errors[0])->toContain('Row 4')
        ->and(Practice::where('client_code', 'AMG')->count())->toBe(1)
        ->and(Practice::where('client_code', 'BMG')->count())->toBe(1)
        ->and(User::where('email', 'alpha-practice@example.com')->exists())->toBeTrue()
        ->and(User::where('email', 'beta-practice@example.com')->exists())->toBeTrue()
        ->and(Practice::where('client_code', 'AMG')->first()->primaryAddress?->city)->toBe('Austin');
});

test('provider excel import creates a provider, links matching practice in provider_practice, and rejects duplicate npi and email', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $service = app(SpreadsheetImportService::class);
    $scope = app(\App\Services\AdminScopeService::class);

    $practiceUser = User::create([
        'name' => 'Alpha Medical Group',
        'email' => 'alpha-group@example.com',
        'password' => 'password123',
    ]);
    $practice = Practice::create([
        'user_id' => $practiceUser->id,
        'legal_name' => 'Alpha Medical Group',
        'client_code' => 'AMG',
        'email' => 'alpha-group@example.com',
        'status' => 'active',
    ]);

    $scopedAdmin = Admin::create([
        'name' => 'Scoped Specialist',
        'username' => 'scopedspecialist',
        'email' => 'scoped-specialist@example.com',
        'password' => 'password123',
        'status' => 1,
    ]);
    $scopedAdmin->assignRole(\App\Enums\AdminRole::CredentialingExecutive->value);
    $scopedAdmin->practices()->sync([$practice->id]);

    $headers = SpreadsheetImportService::PROVIDER_COLUMNS;
    $row = fn (string $name, string $email, string $npi) => array_map(
        fn (string $col) => ([
            'name' => $name,
            'email' => $email,
            'npi' => $npi,
            'specialty' => 'Family Medicine',
            'practice' => 'Alpha Medical Group',
            'address' => '200 Oak Ave',
            'city' => 'Dallas',
            'state' => 'TX',
            'zip' => '75201',
            'status' => 'inactive',
        ])[$col] ?? '',
        $headers
    );

    $first = spreadsheetImportFile('providers.xlsx', $headers, [
        $row('Dr. First Import', 'first-provider@example.com', '1111111111'),
    ]);

    $ok = $service->importProviders($first, $admin->id);
    expect($ok->success_rows)->toBe(1)->and($ok->failed_rows)->toBe(0);

    $firstProvider = ProviderDetails::where('npi', '1111111111')->firstOrFail();
    expect($firstProvider->practices->pluck('id')->all())->toContain($practice->id)
        ->and((bool) $firstProvider->practices->first()->pivot->primary_flag)->toBeTrue()
        ->and($scope->canAccessProvider($scopedAdmin, $firstProvider))->toBeTrue()
        ->and($scope->scopeProviders(ProviderDetails::query(), $scopedAdmin)->pluck('id')->all())->toContain($firstProvider->id);

    $dupes = spreadsheetImportFile('providers-dupes.xlsx', $headers, [
        $row('Dr. Email Clash', 'first-provider@example.com', '2222222222'),
        $row('Dr. Npi Clash', 'second-provider@example.com', '1111111111'),
        $row('Dr. Second Import', 'second-provider@example.com', '2222222222'),
    ]);

    $batch = $service->importProviders($dupes, $admin->id);

    expect($batch->success_rows)->toBe(1)
        ->and($batch->failed_rows)->toBe(2)
        ->and(ProviderDetails::count())->toBe(2)
        ->and($firstProvider->user->name)->toBe('Dr. First Import')
        ->and(ProviderDetails::where('npi', '2222222222')->first()->practice)->toBe('Alpha Medical Group');
});

test('bulk import page downloads excel templates', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();

    Livewire::actingAs($admin, 'admin')
        ->test(BulkImportPage::class)
        ->call('downloadPracticeTemplate')
        ->assertFileDownloaded('practice-import-template.xlsx');

    Livewire::actingAs($admin, 'admin')
        ->test(BulkImportPage::class)
        ->call('downloadProviderTemplate')
        ->assertFileDownloaded('provider-import-template.xlsx');
});
