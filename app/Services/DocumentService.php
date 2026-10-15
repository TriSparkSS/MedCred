<?php

namespace App\Services;

use App\Enums\DocumentVerificationStatus;
use App\Events\DocumentVerified;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentType;
use App\Support\UsStates;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class DocumentService
{
    public function upload(array $data, UploadedFile $file, ?int $adminId = null, ?int $userId = null): Document
    {
        return DB::transaction(function () use ($data, $file, $adminId, $userId) {
            $syncCredential = (bool) ($data['sync_credential'] ?? false);
            unset($data['sync_credential']);
            $data = $this->normalizePayload($data);

            $data['state'] = UsStates::normalize($data['state'] ?? null);

            $existing = $this->findExistingStateDocument($data);
            if ($existing) {
                $existing->addVersion($file, $adminId, 'Replacement for same type and state', $userId);
                $existing->update([
                    'title' => $data['title'] ?? $existing->title,
                    'document_number' => $data['document_number'] ?? $existing->document_number,
                    'effective_date' => $data['effective_date'] ?? $existing->effective_date,
                    'expiry_date' => $data['expiry_date'] ?? $existing->expiry_date,
                    'verification_status' => DocumentVerificationStatus::Uploaded->value,
                    'verified_at' => null,
                    'verified_by_admin_id' => null,
                    'rejection_reason' => null,
                ]);

                $document = $existing->fresh(['versions', 'documentType', 'provider']);
            } else {
                $document = Document::createWithFile([
                    ...$data,
                    'verification_status' => DocumentVerificationStatus::Uploaded->value,
                ], $file, $adminId, $userId);
            }

            if ($document->credentialing_case_id) {
                $document->credentialingCase?->syncChecklistFromDocument($document);
            }

            AuditLog::record('document.uploaded', $document, $adminId);

            if ($syncCredential) {
                app(ProviderCredentialService::class)->syncFromDocument($document->fresh('documentType'));
            }

            return $document->fresh(['versions', 'documentType']);
        });
    }

    protected function findExistingStateDocument(array $data): ?Document
    {
        $providerId = $data['provider_id'] ?? null;
        $practiceId = $data['practice_id'] ?? null;
        $caseId = $data['credentialing_case_id'] ?? null;
        $payerId = $data['payer_id'] ?? null;
        $typeId = $data['document_type_id'] ?? null;
        $state = $data['state'] ?? null;

        if ((! $providerId && ! $practiceId && ! $caseId) || ! $typeId || ! $state) {
            return null;
        }

        $type = DocumentType::find($typeId);
        if (! $type?->is_state_specific) {
            return null;
        }

        return Document::query()
            ->where('provider_id', $providerId)
            ->where('practice_id', $practiceId)
            ->where('credentialing_case_id', $caseId)
            ->where('payer_id', $payerId)
            ->where('document_type_id', $typeId)
            ->where('state', $state)
            ->latest('id')
            ->first();
    }

    public function update(Document $document, array $data, ?UploadedFile $file = null, ?int $adminId = null): Document
    {
        return DB::transaction(function () use ($document, $data, $file, $adminId) {
            $syncCredential = (bool) ($data['sync_credential'] ?? false);
            unset($data['sync_credential']);
            $data = $this->normalizePayload($data);

            $data['state'] = UsStates::normalize($data['state'] ?? null);

            $document->update([
                'title' => $data['title'] ?? $document->title,
                'document_number' => $data['document_number'] ?? $document->document_number,
                'document_type_id' => array_key_exists('document_type_id', $data) ? $data['document_type_id'] : $document->document_type_id,
                'provider_id' => array_key_exists('provider_id', $data) ? $data['provider_id'] : $document->provider_id,
                'practice_id' => array_key_exists('practice_id', $data) ? $data['practice_id'] : $document->practice_id,
                'credentialing_case_id' => array_key_exists('credentialing_case_id', $data) ? $data['credentialing_case_id'] : $document->credentialing_case_id,
                'effective_date' => $data['effective_date'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'state' => array_key_exists('state', $data) ? $data['state'] : $document->state,
            ]);

            if ($file) {
                $document->addVersion($file, $adminId, 'Updated file');
                $document->update([
                    'verification_status' => DocumentVerificationStatus::Uploaded->value,
                    'verified_at' => null,
                    'verified_by_admin_id' => null,
                    'rejection_reason' => null,
                ]);
            }

            AuditLog::record('document.updated', $document, $adminId);

            if ($syncCredential) {
                app(ProviderCredentialService::class)->syncFromDocument($document->fresh('documentType'));
            }

            if ($document->credentialing_case_id) {
                $document->credentialingCase?->syncChecklistFromDocument($document->fresh());
            }

            return $document->fresh(['versions', 'documentType']);
        });
    }

    public function verify(Document $document, ?int $adminId = null): Document
    {
        $document->update([
            'verification_status' => DocumentVerificationStatus::Verified->value,
            'verified_by_admin_id' => $adminId,
            'verified_at' => now(),
            'rejection_reason' => null,
        ]);

        DocumentVerified::dispatch($document->fresh(), $adminId);

        return $document->fresh();
    }

    public function reject(Document $document, string $reason, ?int $adminId = null): Document
    {
        $document->update([
            'verification_status' => DocumentVerificationStatus::Rejected->value,
            'rejection_reason' => $reason,
            'verified_by_admin_id' => $adminId,
            'verified_at' => now(),
        ]);

        AuditLog::record('document.rejected', $document, $adminId, ['reason' => $reason]);

        if ($document->credentialing_case_id) {
            $document->credentialingCase?->addActivity(
                'document',
                "Document rejected: {$document->title} — {$reason}",
                $adminId
            );
        }

        return $document->fresh();
    }

    public function supersede(Document $document, UploadedFile $file, ?int $adminId = null): Document
    {
        return DB::transaction(function () use ($document, $file, $adminId) {
            $document->update(['verification_status' => DocumentVerificationStatus::Superseded->value]);
            $document->addVersion($file, $adminId, 'Superseded by new version');

            $document->update([
                'verification_status' => DocumentVerificationStatus::Uploaded->value,
                'verified_at' => null,
                'verified_by_admin_id' => null,
                'rejection_reason' => null,
            ]);

            AuditLog::record('document.superseded', $document, $adminId);

            return $document->fresh();
        });
    }

    public function markExpired(Document $document): Document
    {
        $document->update(['verification_status' => DocumentVerificationStatus::Expired->value]);

        return $document->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizePayload(array $data): array
    {
        foreach (['effective_date', 'expiry_date'] as $field) {
            if (($data[$field] ?? null) === '') {
                $data[$field] = null;
            }
        }

        foreach (['provider_id', 'practice_id', 'credentialing_case_id', 'document_type_id', 'payer_id'] as $idField) {
            if (array_key_exists($idField, $data) && $data[$idField] === '') {
                $data[$idField] = null;
            }
        }

        return $data;
    }
}
