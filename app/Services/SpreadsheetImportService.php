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
        'license_name',
        'license_effective_date',
        'license_expiry_date',
        'status',
        'address1',
        'address2',
        'city',
        'state',
        'zip_code',
        'county',
        'country',
        'alt_location_name',
        'alt_address1',
        'alt_address2',
        'alt_city',
        'alt_state',
        'alt_zip_code',
        'alt_county',
        'alt_country',
        'alt_phone',
        'alt_fax',
        'mailing_location_name',
        'mailing_address1',
        'mailing_address2',
        'mailing_city',
        'mailing_state',
        'mailing_zip_code',
        'mailing_county',
        'mailing_country',
        'mailing_phone',
        'mailing_fax',
        'billing_location_name',
        'billing_address1',
        'billing_address2',
        'billing_city',
        'billing_state',
        'billing_zip_code',
        'billing_county',
        'billing_country',
        'billing_phone',
        'billing_fax',
        'bank_name',
        'bank_account',
        'bank_routing_number',
        'bank_phone',
        'bank_address',
        'facility_1_name',
        'facility_1_address1',
        'facility_1_address2',
        'facility_1_city',
        'facility_1_state',
        'facility_1_zip_code',
        'facility_1_phone',
        'facility_1_fax',
        'facility_1_npi',
        'facility_1_taxonomy_code',
        'facility_1_is_primary',
        'facility_2_name',
        'facility_2_address1',
        'facility_2_address2',
        'facility_2_city',
        'facility_2_state',
        'facility_2_zip_code',
        'facility_2_phone',
        'facility_2_fax',
        'facility_2_npi',
        'facility_2_taxonomy_code',
        'facility_2_is_primary',
        'facility_3_name',
        'facility_3_address1',
        'facility_3_address2',
        'facility_3_city',
        'facility_3_state',
        'facility_3_zip_code',
        'facility_3_phone',
        'facility_3_fax',
        'facility_3_npi',
        'facility_3_taxonomy_code',
        'facility_3_is_primary',
        'facility_4_name',
        'facility_4_address1',
        'facility_4_address2',
        'facility_4_city',
        'facility_4_state',
        'facility_4_zip_code',
        'facility_4_phone',
        'facility_4_fax',
        'facility_4_npi',
        'facility_4_taxonomy_code',
        'facility_4_is_primary',
        'facility_5_name',
        'facility_5_address1',
        'facility_5_address2',
        'facility_5_city',
        'facility_5_state',
        'facility_5_zip_code',
        'facility_5_phone',
        'facility_5_fax',
        'facility_5_npi',
        'facility_5_taxonomy_code',
        'facility_5_is_primary',
        'password',
    ];

    public const PROVIDER_COLUMNS = [
        'name',
        'email',
        'phone',
        'dob',
        'ssn',
        'npi',
        'specialty',
        'degree',
        'provider_type',
        'practice',
        'address',
        'city',
        'state',
        'zip',
        'home_address',
        'home_city',
        'home_state',
        'home_zip',
        'caqh_id',
        'taxonomy_code',
        'malpractice_carrier',
        'malpractice_policy_number',
        'malpractice_coverage_each_occurrence',
        'malpractice_coverage_aggregate',
        'malpractice_effective_date',
        'malpractice_expiry',
        'status',
        'status_remarks',
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
        $sampleMap = [
            'legal_name' => 'Sample Medical Group',
            'client_code' => 'SMG',
            'email' => 'sample-practice@example.com',
            'phone' => '5125550100',
            'status' => 'pending',
            'address1' => '100 Main St',
            'city' => 'Austin',
            'state' => 'TX',
            'zip_code' => '78701',
            'country' => 'United States',
        ];
        $sampleRow = array_map(fn (string $col) => $sampleMap[$col] ?? '', self::PRACTICE_COLUMNS);

        return $this->downloadTemplate(
            'practice-import-template.xlsx',
            self::PRACTICE_COLUMNS,
            $sampleRow,
        );
    }

    public function downloadProviderTemplate(): StreamedResponse
    {
        $sampleMap = [
            'name' => 'Dr. Jane Sample',
            'email' => 'jane.sample@example.com',
            'phone' => '5125550101',
            'dob' => '1980-05-15',
            'ssn' => '123-45-6789',
            'npi' => '1234567890',
            'specialty' => 'Internal Medicine',
            'degree' => 'MD',
            'provider_type' => 'Physician',
            'practice' => 'Sample Medical Group',
            'address' => '100 Main St',
            'city' => 'Austin',
            'state' => 'TX',
            'zip' => '78701',
            'status' => 'active',
        ];
        $sampleRow = array_map(fn (string $col) => $sampleMap[$col] ?? '', self::PROVIDER_COLUMNS);

        return $this->downloadTemplate(
            'provider-import-template.xlsx',
            self::PROVIDER_COLUMNS,
            $sampleRow,
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
        $clientCode = Practice::normalizeClientCode($this->cell($data, 'client_code'));

        if (! Practice::isValidClientCode($clientCode)) {
            throw new InvalidArgumentException('client_code must be 3–12 letters or numbers');
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
            'license_name' => $this->optional($data, 'license_name'),
            'license_effective_date' => $this->optional($data, 'license_effective_date'),
            'license_expiry_date' => $this->optional($data, 'license_expiry_date'),
            'bank_name' => $this->optional($data, 'bank_name'),
            'bank_account' => $this->optional($data, 'bank_account'),
            'bank_routing_number' => $this->optional($data, 'bank_routing_number'),
            'bank_phone' => $this->optional($data, 'bank_phone'),
            'bank_address' => $this->optional($data, 'bank_address'),
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

        $addressPrefixes = [
            'alternative' => 'alt',
            'mailing' => 'mailing',
            'billing' => 'billing',
        ];

        foreach ($addressPrefixes as $type => $prefix) {
            $addr1 = $this->optional($data, "{$prefix}_address1");
            if ($addr1) {
                $addrStateRaw = $this->optional($data, "{$prefix}_state");
                $addrState = $addrStateRaw ? (UsStates::normalize($addrStateRaw) ?? $addrStateRaw) : $state;

                $practice->addresses()->create([
                    'type' => $type,
                    'location_name' => $this->optional($data, "{$prefix}_location_name"),
                    'address1' => $addr1,
                    'address2' => $this->optional($data, "{$prefix}_address2"),
                    'city' => $this->optional($data, "{$prefix}_city") ?? $this->cell($data, 'city'),
                    'state' => $addrState,
                    'zip_code' => $this->optional($data, "{$prefix}_zip_code") ?? $this->cell($data, 'zip_code'),
                    'county' => $this->optional($data, "{$prefix}_county"),
                    'country' => $this->optional($data, "{$prefix}_country") ?: 'United States',
                    'phone' => $this->optional($data, "{$prefix}_phone"),
                    'fax' => $this->optional($data, "{$prefix}_fax"),
                    'status' => 'active',
                ]);
            }
        }

        for ($i = 1; $i <= 5; $i++) {
            $facName = $this->optional($data, "facility_{$i}_name");
            $facAddr1 = $this->optional($data, "facility_{$i}_address1");
            if ($facName && $facAddr1) {
                $facStateRaw = $this->optional($data, "facility_{$i}_state");
                $facState = $facStateRaw ? (UsStates::normalize($facStateRaw) ?? $facStateRaw) : $state;

                $practice->locations()->create([
                    'name' => $facName,
                    'address1' => $facAddr1,
                    'address2' => $this->optional($data, "facility_{$i}_address2"),
                    'city' => $this->optional($data, "facility_{$i}_city") ?? $this->cell($data, 'city'),
                    'state' => $facState,
                    'zip_code' => $this->optional($data, "facility_{$i}_zip_code") ?? $this->cell($data, 'zip_code'),
                    'country' => 'United States',
                    'phone' => $this->optional($data, "facility_{$i}_phone"),
                    'fax' => $this->optional($data, "facility_{$i}_fax"),
                    'npi' => $this->optional($data, "facility_{$i}_npi"),
                    'taxonomy_code' => $this->optional($data, "facility_{$i}_taxonomy_code"),
                    'status' => 'active',
                    'is_primary' => $this->optional($data, "facility_{$i}_is_primary") !== null
                        ? $this->toBoolean($this->optional($data, "facility_{$i}_is_primary"))
                        : ($i === 1),
                ]);
            }
        }

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

        $status = $this->normalizeProviderStatus($this->optional($data, 'status'));
        $statusRemarks = $this->optional($data, 'status_remarks');

        if ($status === ProviderStatus::ON_HOLD->value && blank($statusRemarks)) {
            throw new InvalidArgumentException('status_remarks is required when status is on_hold');
        }

        $state = $this->requireState($data);
        $homeStateRaw = $this->optional($data, 'home_state');
        $homeState = $homeStateRaw ? (UsStates::normalize($homeStateRaw) ?? $homeStateRaw) : null;

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
            'dob' => $this->optional($data, 'dob'),
            'ssn' => $this->optional($data, 'ssn'),
            'npi' => $npi,
            'specialty_id' => $specialtyId,
            'degree' => $this->optional($data, 'degree'),
            'provider_type' => $this->optional($data, 'provider_type'),
            'status' => $status,
            'status_remarks' => $statusRemarks,
            'caqh_id' => $this->optional($data, 'caqh_id'),
            'taxonomy_code' => $this->optional($data, 'taxonomy_code'),
            'malpractice_carrier' => $this->optional($data, 'malpractice_carrier'),
            'malpractice_policy_number' => $this->optional($data, 'malpractice_policy_number'),
            'malpractice_coverage_each_occurrence' => $this->optional($data, 'malpractice_coverage_each_occurrence'),
            'malpractice_coverage_aggregate' => $this->optional($data, 'malpractice_coverage_aggregate'),
            'malpractice_effective_date' => $this->optional($data, 'malpractice_effective_date'),
            'malpractice_expiry' => $this->optional($data, 'malpractice_expiry'),
            'practice' => $this->cell($data, 'practice'),
            'address' => $this->cell($data, 'address'),
            'city' => $this->cell($data, 'city'),
            'state' => $state,
            'zip' => $this->cell($data, 'zip'),
            'home_address' => $this->optional($data, 'home_address'),
            'home_city' => $this->optional($data, 'home_city'),
            'home_state' => $homeState,
            'home_zip' => $this->optional($data, 'home_zip'),
        ]);

        $practiceName = $this->cell($data, 'practice');
        if ($practiceName !== '') {
            $practice = Practice::where('legal_name', $practiceName)
                ->orWhere('dba_name', $practiceName)
                ->orWhere('client_code', strtoupper($practiceName))
                ->first();

            if ($practice) {
                $provider->practices()->syncWithoutDetaching([
                    $practice->id => ['primary_flag' => true],
                ]);
            }
        }
    }

    protected function toBoolean(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'y'], true);
    }

    protected function normalizeProviderStatus(?string $value): string
    {
        $status = strtolower(trim(str_replace(['-', ' '], '_', $value ?? ProviderStatus::INACTIVE->value)));

        $mapped = match ($status) {
            'approved', 'active' => ProviderStatus::ACTIVE->value,
            'pending', 'rejected', 'inactive' => ProviderStatus::INACTIVE->value,
            'on_hold', 'onhold' => ProviderStatus::ON_HOLD->value,
            default => null,
        };

        if ($mapped === null) {
            throw new InvalidArgumentException('status must be active, inactive, or on_hold');
        }

        return $mapped;
    }
}
