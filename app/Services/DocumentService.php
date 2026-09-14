<?php

namespace App\Services;

use App\Enums\DocumentVerificationStatus;
use App\Events\DocumentVerified;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentType;
use App\Support\UsStates;
use Illuminate\Http\UploadedFile;

class DocumentService
{
    public function upload(array $data, UploadedFile $file, ?int $adminId = null, ?int $userId = null): Document
    {
        $syncCredential = (bool) ($data['sync_credential'] ?? false);
        unset($data['sync_credential']);

        $data['state'] = UsStates::normalize($data['state'] ?? null);

        $existing = $this->findExistingStateDocument($data);
        if ($existing) {
            $existing->addVersion($file, $adminId, 'Replacement for same type and state', $userId);
            $existing->update([
                'title' => $data['title'] ?? $existing->title,
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
    }

    protected function findExistingStateDocument(array $data): ?Document
    {
        $providerId = $data['provider_id'] ?? null;
        $typeId = $data['document_type_id'] ?? null;
        $state = $data['state'] ?? null;

        if (! $providerId || ! $typeId || ! $state) {
            return null;
        }

        $type = DocumentType::find($typeId);
        if (! $type?->is_state_specific) {
            return null;
        }

        return Document::query()
            ->where('provider_id', $providerId)
            ->where('document_type_id', $typeId)
            ->where('state', $state)
            ->latest('id')
            ->first();
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
    }

    public function markExpired(Document $document): Document
    {
        $document->update(['verification_status' => DocumentVerificationStatus::Expired->value]);

        return $document->fresh();
    }
}
