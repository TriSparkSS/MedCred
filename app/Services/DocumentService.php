<?php

namespace App\Services;

use App\Enums\DocumentVerificationStatus;
use App\Events\DocumentVerified;
use App\Models\AuditLog;
use App\Models\Document;
use Illuminate\Http\UploadedFile;

class DocumentService
{
    public function upload(array $data, UploadedFile $file, ?int $adminId = null, ?int $userId = null): Document
    {
        $document = Document::createWithFile([
            ...$data,
            'verification_status' => DocumentVerificationStatus::Uploaded->value,
        ], $file, $adminId, $userId);

        if ($document->credentialing_case_id) {
            $document->credentialingCase?->syncChecklistFromDocument($document);
        }

        AuditLog::record('document.uploaded', $document, $adminId);

        return $document;
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
