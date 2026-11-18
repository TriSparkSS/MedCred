<?php

namespace App\Livewire\Concerns;

use App\Models\DocumentType;
use App\Services\DocumentService;
use App\Services\FileMalwareScanner;
use App\Support\UsStates;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Add Practice / Add Provider: document rows held in component state and uploaded
 * through DocumentService (malware scan + encryption) when the record is created.
 *
 * The host component declares one public array property per group and describes the
 * groups in pendingDocumentGroups(), e.g.
 *   'bankDocuments' => ['locked_type' => 'Bank Document', 'state_aware' => false].
 */
trait ManagesPendingDocuments
{
    public const DOCUMENT_UPLOAD_PERMISSION = 'admin.documents.upload';

    /**
     * @return array<string, array{locked_type: ?string, state_aware: bool}>
     */
    abstract protected function pendingDocumentGroups(): array;

    public function canUploadPendingDocuments(): bool
    {
        return (bool) Auth::guard('admin')->user()?->can(self::DOCUMENT_UPLOAD_PERMISSION);
    }

    public function addDocumentRow(string $group): void
    {
        $config = $this->documentGroupConfig($group);
        abort_unless($this->canUploadPendingDocuments(), 403);

        $rows = $this->{$group};
        $rows[] = [
            'title' => $config['locked_type'] ?? '',
            'document_number' => '',
            'document_type_id' => '',
            'effective_date' => '',
            'expiry_date' => '',
            'state' => '',
            'file' => null,
        ];
        $this->{$group} = $rows;
    }

    public function removeDocumentRow(string $group, int $index): void
    {
        $this->documentGroupConfig($group);

        $rows = $this->{$group};
        unset($rows[$index]);
        $this->{$group} = array_values($rows);
        $this->resetValidation();
    }

    protected function documentGroupConfig(string $group): array
    {
        $groups = $this->pendingDocumentGroups();
        abort_unless(array_key_exists($group, $groups), 404);

        return $groups[$group] + ['locked_type' => null, 'state_aware' => false];
    }

    protected function hasPendingDocuments(): bool
    {
        foreach (array_keys($this->pendingDocumentGroups()) as $group) {
            if ($this->{$group} !== []) {
                return true;
            }
        }

        return false;
    }

    protected function pendingDocumentRules(): array
    {
        $rules = [];

        foreach ($this->pendingDocumentGroups() as $group => $config) {
            $rows = $this->{$group};
            if ($rows === []) {
                continue;
            }

            $rules += DocumentService::entityDocumentRules("{$group}.*");
            $rules["{$group}.*.file"] = 'required|'.DocumentService::FILE_RULES;

            foreach ($rows as $index => $row) {
                $stateSpecific = ($config['state_aware'] ?? false)
                    && filled($row['document_type_id'] ?? null)
                    && (bool) DocumentType::find($row['document_type_id'])?->is_state_specific;

                $rules["{$group}.{$index}.state"] = $stateSpecific
                    ? ['required', 'string', 'size:2', Rule::in(UsStates::codes())]
                    : 'nullable|string|max:50';
            }
        }

        return $rules;
    }

    protected function pendingDocumentAttributes(): array
    {
        $attributes = [];

        foreach (array_keys($this->pendingDocumentGroups()) as $group) {
            $attributes += [
                "{$group}.*.title" => 'document name',
                "{$group}.*.document_number" => 'document number',
                "{$group}.*.document_type_id" => 'document type',
                "{$group}.*.effective_date" => 'effective date',
                "{$group}.*.expiry_date" => 'expiration date',
                "{$group}.*.state" => 'state',
                "{$group}.*.file" => 'file',
            ];
        }

        return $attributes;
    }

    protected function assertCanSavePendingDocuments(): void
    {
        if ($this->hasPendingDocuments()) {
            abort_unless($this->canUploadPendingDocuments(), 403);
        }
    }

    /**
     * Run the malware scan before anything is written so a bad file points at its own row.
     */
    protected function scanPendingDocuments(): void
    {
        $scanner = app(FileMalwareScanner::class);
        $adminId = Auth::guard('admin')->id();
        $errors = [];

        foreach (array_keys($this->pendingDocumentGroups()) as $group) {
            foreach ($this->{$group} as $index => $row) {
                $file = $row['file'] ?? null;
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                try {
                    $scanner->assertClean($file, $adminId);
                } catch (ValidationException $e) {
                    $errors["{$group}.{$index}.file"] = collect($e->errors())->flatten()->first();
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    protected function persistPendingDocuments(?int $practiceId, ?int $providerId): void
    {
        $service = app(DocumentService::class);
        $adminId = Auth::guard('admin')->id();

        foreach ($this->pendingDocumentGroups() as $group => $config) {
            $lockedTypeId = filled($config['locked_type'] ?? null)
                ? DocumentType::firstOrCreate(
                    ['name' => $config['locked_type']],
                    ['is_active' => true, 'is_state_specific' => false]
                )->id
                : null;

            foreach ($this->{$group} as $row) {
                $payload = [
                    'title' => $row['title'],
                    'document_number' => ($row['document_number'] ?? '') !== '' ? $row['document_number'] : null,
                    'document_type_id' => $lockedTypeId ?? (($row['document_type_id'] ?? '') !== '' ? (int) $row['document_type_id'] : null),
                    'effective_date' => $row['effective_date'] ?? null,
                    'expiry_date' => $row['expiry_date'] ?? null,
                ];

                if ($providerId) {
                    // Same defaults as the provider Edit page documents section.
                    $payload['state'] = ($row['state'] ?? '') !== '' ? $row['state'] : null;
                    $payload['sync_credential'] = true;
                }

                $service->uploadForEntity($payload, $row['file'], $practiceId, $providerId, $adminId);
            }
        }
    }

    protected function pendingDocumentTypes(bool $includeStateSpecific)
    {
        $query = DocumentType::where('is_active', true)->orderBy('name');

        if (! $includeStateSpecific) {
            $query->where('is_state_specific', false);
        }

        return $query->get(['id', 'name', 'is_state_specific']);
    }
}
