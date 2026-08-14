<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ImportBatch;
use App\Models\ProviderDetails;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CsvImportService
{
    public function importProviders(UploadedFile $file, ?int $adminId = null): ImportBatch
    {
        $batch = ImportBatch::create([
            'type' => 'providers',
            'filename' => $file->getClientOriginalName(),
            'admin_id' => $adminId,
        ]);

        $handle = fopen($file->getRealPath(), 'r');
        $headers = array_map('strtolower', array_map('trim', fgetcsv($handle) ?: []));
        $errors = [];
        $success = 0;
        $failed = 0;
        $total = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $total++;
            $data = array_combine($headers, array_pad($row, count($headers), ''));

            try {
                DB::transaction(function () use ($data) {
                    $this->createProviderFromRow($data);
                });
                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "Row {$total}: " . $e->getMessage();
            }
        }

        fclose($handle);

        $batch->update([
            'total_rows' => $total,
            'success_rows' => $success,
            'failed_rows' => $failed,
            'errors' => $errors ?: null,
        ]);

        return $batch->fresh();
    }

    protected function createProviderFromRow(array $data): void
    {
        $email = trim($data['email'] ?? '');
        $name = trim($data['name'] ?? '');
        $npi = trim($data['npi'] ?? '');

        if (! $email || ! $name) {
            throw new \InvalidArgumentException('name and email are required');
        }

        if (User::where('email', $email)->exists()) {
            throw new \InvalidArgumentException("email {$email} already exists");
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'phone' => trim($data['phone'] ?? '') ?: null,
            'password' => Hash::make(trim($data['password'] ?? '') ?: 'ChangeMe123!'),
        ]);

        if (method_exists($user, 'assignRole')) {
            $user->assignRole('provider');
        }

        $specialtyId = null;
        if ($specialtyName = trim($data['specialty'] ?? '')) {
            $specialtyId = Specialty::firstOrCreate(['name' => $specialtyName])->id;
        }

        ProviderDetails::create([
            'user_id' => $user->id,
            'npi' => $npi ?: null,
            'specialty_id' => $specialtyId,
            'status' => \App\Enums\ProviderStatus::PENDING,
        ]);
    }
}
