<?php

namespace App\Services;

use App\Models\CredentialingCase;
use App\Models\Document;
use App\Models\DocumentType;

class BillingReadinessService
{
    public function requiredFields(): array
    {
        return [
            'approval_date',
            'effective_date',
            'payer_provider_id',
        ];
    }

    public function missingFields(CredentialingCase $case): array
    {
        $missing = [];

        foreach ($this->requiredFields() as $field) {
            if (empty($case->{$field})) {
                $missing[] = $field;
            }
        }

        if (! $this->hasApprovalLetter($case)) {
            $missing[] = 'approval_letter';
        }

        return $missing;
    }

    public function hasApprovalLetter(CredentialingCase $case): bool
    {
        $typeId = DocumentType::where('name', 'like', '%approval%')->value('id');

        if (! $typeId) {
            return Document::where('credentialing_case_id', $case->id)
                ->where('verification_status', 'verified')
                ->exists();
        }

        return Document::where('credentialing_case_id', $case->id)
            ->where('document_type_id', $typeId)
            ->where('verification_status', 'verified')
            ->exists();
    }

    public function canMarkReadyToBill(CredentialingCase $case): bool
    {
        return count($this->missingFields($case)) === 0;
    }

    public function updateBillingFields(CredentialingCase $case, array $data, ?int $adminId = null): CredentialingCase
    {
        $case->update($data);

        $ready = $this->canMarkReadyToBill($case);
        $case->update(['ready_to_bill' => $ready]);

        if ($ready) {
            $case->addActivity('billing', 'Case marked ready to bill', $adminId);
        }

        return $case->fresh();
    }

    public function notifyBilling(CredentialingCase $case, ?int $adminId = null): CredentialingCase
    {
        if (! $this->canMarkReadyToBill($case)) {
            throw new \InvalidArgumentException('Cannot notify billing — required fields incomplete.');
        }

        $case->update([
            'billing_notified' => true,
            'ready_to_bill' => true,
        ]);

        $case->addActivity('billing', 'Billing team notified', $adminId);

        return $case->fresh();
    }
}
