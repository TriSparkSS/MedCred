<?php

namespace App\Services;

use App\Enums\PracticeStatus;
use App\Enums\ProviderCredentialType;
use App\Enums\ProviderStatus;
use App\Models\Admin;
use App\Models\ImportBatch;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\Specialty;
use App\Models\User;
use App\Support\UsStates;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SpreadsheetImportService
{
    public function __construct(
        protected AdminScopeService $scope,
        protected ProviderCredentialService $credentials,
    ) {}

    public const PRACTICE_COLUMNS = [
        'legal_name',
        'client_code',
        'email',
        'dba_name',
        'ein_tin',
        'group_npi',
        'taxonomy_code',
        'phone',
        'fax',
        'website',
        'license_number',
        'status',
        'address1',
        'address2',
        'city',
        'state',
        'zip_code',
        'county',
        'country',
        'password',
    ];

    public const PROVIDER_COLUMNS = [
        'name',
        'email',
        'phone',
        'npi',
        'specialty',
        'practice',
        'address',
        'city',
        'state',
        'zip',
        'caqh_id',
        'taxonomy_code',
        'pecos_id',
        'pecos_enrolled',
        'malpractice_carrier',
        'malpractice_policy_number',
        'malpractice_coverage_each_occurrence',
        'malpractice_coverage_aggregate',
        'malpractice_effective_date',
        'malpractice_expiry',
        'board_certification',
        'board_cert_expiry',
        'license_number',
        'license_state',
        'license_expiry',
        'status',
        'password',
    ];

    public function importPractices(UploadedFile $file, ?int $adminId = null): ImportBatch
    {
        return $this->importFile($file, 'practices', $adminId, fn (array $row) => $this->createPracticeFromRow($row, $adminId));
    }

    public function importProviders(UploadedFile $file, ?int $adminId = null): ImportBatch
    {
        return $this->importFile($file, 'providers', $adminId, fn (array $row) => $this->createProviderFromRow($row));
    }

    public function downloadPracticeTemplate(): StreamedResponse
    {
        return $this->downloadTemplate(
            'practice-import-template.xlsx',
            self::PRACTICE_COLUMNS,
            [
                'Sample Medical Group',
                'SMG',
                'sample-practice@example.com',
                '',
                '',
                '',
                '',
                '5125550100',
                '',
                '',
                '',
                'pending',
                '100 Main St',
                '',
                'Austin',
                'TX',
                '78701',
                '',
                'United States',
                '',
            ],
        );
    }

    public function downloadProviderTemplate(): StreamedResponse
    {
        return $this->downloadTemplate(
            'provider-import-template.xlsx',
            self::PROVIDER_COLUMNS,
            [
                'Dr. Jane Sample',
                'jane.sample@example.com',
                '5125550101',
                '1234567890',
                'Internal Medicine',
                'Sample Medical Group',
                '100 Main St',
                'Austin',
                'TX',
                '78701',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                'pending',
                '',
            ],
        );
    }

    protected function downloadTemplate(string $filename, array $headers, array $sample): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $sample) {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray([$headers, $sample]);
            $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true);

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    protected function importFile(UploadedFile $file, string $type, ?int $adminId, callable $creator): ImportBatch
    {
        $batch = ImportBatch::create([
            'type' => $type,
            'filename' => $file->getClientOriginalName(),
            'admin_id' => $adminId,
        ]);

        $rows = $this->readRows($file);
        $errors = [];
        $success = 0;
        $failed = 0;
        $total = 0;

        foreach ($rows as $rowNumber => $data) {
            if ($this->rowIsEmpty($data)) {
                continue;
            }

            $total++;

            try {
                DB::transaction(fn () => $creator($data));
                $success++;
            } catch (Throwable $e) {
                $failed++;
                $errors[] = "Row {$rowNumber}: ".$e->getMessage();
            }
        }

        $batch->update([
            'total_rows' => $total,
            'success_rows' => $success,
            'failed_rows' => $failed,
            'errors' => $errors ?: null,
        ]);

        return $batch->fresh();
    }

    protected function readRows(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $extension = strtolower($file->getClientOriginalExtension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));

        $reader = match ($extension) {
            'xls' => IOFactory::createReader('Xls'),
            'csv', 'txt' => IOFactory::createReader('Csv'),
            default => IOFactory::createReader('Xlsx'),
        };

        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        $spreadsheet->disconnectWorksheets();

        if ($sheet === []) {
            return [];
        }

        $headers = array_map(fn ($header) => $this->normalizeHeader((string) $header), array_shift($sheet) ?? []);
        $rows = [];

        foreach ($sheet as $index => $row) {
            $padded = array_pad($row ?? [], count($headers), null);
            $combined = @array_combine($headers, array_slice($padded, 0, count($headers)));

            if (! is_array($combined)) {
                continue;
            }

            $rows[$index + 2] = $combined;
        }

        return $rows;
    }

    protected function normalizeHeader(string $header): string
    {
        $normalized = strtolower(trim($header));
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? $normalized;

        return trim($normalized, '_');
    }

    protected function rowIsEmpty(array $data): bool
    {
        foreach ($data as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    protected function cell(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return trim((string) $value);
    }

    protected function optional(array $data, string $key): ?string
    {
        $value = $this->cell($data, $key);

        return $value === '' ? null : $value;
    }

    protected function requireCells(array $data, array $keys): void
    {
        $missing = [];

        foreach ($keys as $key) {
            if ($this->cell($data, $key) === '') {
                $missing[] = $key;
            }
        }

        if ($missing !== []) {
            throw new InvalidArgumentException('missing required columns: '.implode(', ', $missing));
        }
    }

    protected function requireState(array $data, string $key = 'state'): string
    {
        $state = UsStates::normalize($this->cell($data, $key));

        if (! $state || ! in_array($state, UsStates::codes(), true)) {
            throw new InvalidArgumentException("{$key} must be a valid US state");
        }

        return $state;
    }

    protected function passwordForRow(array $data): string
    {
        return $this->optional($data, 'password') ?: Str::password(16);
    }

    protected function createPracticeFromRow(array $data, ?int $adminId): void
    {
        $this->requireCells($data, ['legal_name', 'client_code', 'email', 'address1', 'city', 'state', 'zip_code']);

        $email = $this->cell($data, 'email');
        $clientCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $this->cell($data, 'client_code')) ?? '', 0, 3));

        if (strlen($clientCode) !== 3) {
            throw new InvalidArgumentException('client_code must be 3 letters');
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("email {$email} is invalid");
        }

        if (User::where('email', $email)->exists() || Practice::where('email', $email)->exists()) {
            throw new InvalidArgumentException("email {$email} already exists");
        }

        if (Practice::where('client_code', $clientCode)->exists()) {
            throw new InvalidArgumentException("client_code {$clientCode} already exists");
        }

        $ein = $this->optional($data, 'ein_tin');
        if ($ein && Practice::where('ein_tin', $ein)->exists()) {
            throw new InvalidArgumentException("ein_tin {$ein} already exists");
        }

        $groupNpi = $this->optional($data, 'group_npi');
        if ($groupNpi && Practice::where('group_npi', $groupNpi)->exists()) {
            throw new InvalidArgumentException("group_npi {$groupNpi} already exists");
        }

        $status = strtolower($this->optional($data, 'status') ?? PracticeStatus::PENDING->value);
        if (! in_array($status, ['pending', 'active', 'inactive'], true)) {
            throw new InvalidArgumentException('status must be pending, active, or inactive');
        }

        $state = $this->requireState($data);

        $user = User::create([
            'name' => $this->cell($data, 'legal_name'),
            'email' => $email,
            'phone' => $this->optional($data, 'phone'),
            'password' => $this->passwordForRow($data),
        ]);

        if (method_exists($user, 'assignRole')) {
            $user->assignRole('practice');
        }

        $practice = Practice::create([
            'user_id' => $user->id,
            'legal_name' => $this->cell($data, 'legal_name'),
            'client_code' => $clientCode,
            'dba_name' => $this->optional($data, 'dba_name'),
            'ein_tin' => $ein,
            'group_npi' => $groupNpi,
            'taxonomy_code' => $this->optional($data, 'taxonomy_code'),
            'phone' => $this->optional($data, 'phone'),
            'fax' => $this->optional($data, 'fax'),
            'email' => $email,
            'website' => $this->optional($data, 'website'),
            'status' => $status,
            'license_number' => $this->optional($data, 'license_number'),
        ]);

        $practice->addresses()->create([
            'type' => 'primary',
            'address1' => $this->cell($data, 'address1'),
            'address2' => $this->optional($data, 'address2'),
            'city' => $this->cell($data, 'city'),
            'state' => $state,
            'zip_code' => $this->cell($data, 'zip_code'),
            'county' => $this->optional($data, 'county'),
            'country' => $this->optional($data, 'country') ?: 'United States',
            'phone' => $this->optional($data, 'phone'),
            'fax' => $this->optional($data, 'fax'),
            'status' => 'active',
        ]);

        if ($adminId) {
            $admin = Admin::find($adminId);
            if ($admin && $this->scope->isPracticeScoped($admin)) {
                $admin->practices()->syncWithoutDetaching([$practice->id]);
            }
        }
    }

    protected function createProviderFromRow(array $data): void
    {
        $this->requireCells($data, ['name', 'email', 'npi', 'practice', 'address', 'city', 'state', 'zip']);

        $email = $this->cell($data, 'email');
        $npi = $this->cell($data, 'npi');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("email {$email} is invalid");
        }

        if (User::where('email', $email)->exists()) {
            throw new InvalidArgumentException("email {$email} already exists");
        }

        if (ProviderDetails::where('npi', $npi)->exists()) {
            throw new InvalidArgumentException("npi {$npi} already exists");
        }

        $status = strtolower($this->optional($data, 'status') ?? ProviderStatus::PENDING->value);
        if (! in_array($status, ['pending', 'approved', 'rejected'], true)) {
            throw new InvalidArgumentException('status must be pending, approved, or rejected');
        }

        $state = $this->requireState($data);

        $user = User::create([
            'name' => $this->cell($data, 'name'),
            'email' => $email,
            'phone' => $this->optional($data, 'phone'),
            'password' => $this->passwordForRow($data),
        ]);

        if (method_exists($user, 'assignRole')) {
            $user->assignRole('provider');
        }

        $specialtyId = null;
        if ($specialtyName = $this->optional($data, 'specialty')) {
            $specialtyId = Specialty::firstOrCreate(['name' => $specialtyName])->id;
        }

        $provider = ProviderDetails::create([
            'user_id' => $user->id,
            'npi' => $npi,
            'specialty_id' => $specialtyId,
            'status' => $status,
            'caqh_id' => $this->optional($data, 'caqh_id'),
            'taxonomy_code' => $this->optional($data, 'taxonomy_code'),
            'pecos_id' => $this->optional($data, 'pecos_id'),
            'pecos_enrolled' => $this->toBoolean($this->optional($data, 'pecos_enrolled')),
            'malpractice_carrier' => $this->optional($data, 'malpractice_carrier'),
            'malpractice_policy_number' => $this->optional($data, 'malpractice_policy_number'),
            'malpractice_coverage_each_occurrence' => $this->optional($data, 'malpractice_coverage_each_occurrence'),
            'malpractice_coverage_aggregate' => $this->optional($data, 'malpractice_coverage_aggregate'),
            'malpractice_effective_date' => $this->optional($data, 'malpractice_effective_date'),
            'malpractice_expiry' => $this->optional($data, 'malpractice_expiry'),
            'board_certification' => $this->optional($data, 'board_certification'),
            'board_cert_expiry' => $this->optional($data, 'board_cert_expiry'),
            'practice' => $this->cell($data, 'practice'),
            'address' => $this->cell($data, 'address'),
            'city' => $this->cell($data, 'city'),
            'state' => $state,
            'zip' => $this->cell($data, 'zip'),
        ]);

        $licenseNumber = $this->optional($data, 'license_number');
        $licenseState = $this->optional($data, 'license_state');

        if ($licenseNumber && $licenseState) {
            $this->credentials->save($provider, [
                'credential_type' => ProviderCredentialType::License->value,
                'state' => $licenseState,
                'number' => $licenseNumber,
                'expiry_date' => $this->optional($data, 'license_expiry'),
                'is_primary' => true,
            ]);
        }
    }

    protected function toBoolean(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'y'], true);
    }
}
