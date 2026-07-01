<?php

namespace App\Services;

use App\Enums\TaskType;
use App\Events\DocumentRequestSent;
use App\Events\ProviderResponseReceived;
use App\Models\CredentialingCase;
use App\Models\Document;
use App\Models\Status;
use App\Models\Task;
use App\Support\BusinessDayCalculator;

class FollowUpEngine
{
    public function __construct(
        protected TaskService $taskService,
        protected BusinessDayCalculator $businessDays,
    ) {
    }

    public function onDocumentRequestSent(DocumentRequestSent $event): void
    {
        $case = $event->case->fresh(['provider', 'assignedAdmin']);

        $this->taskService->ensureTask(
            'document_request:' . $case->id,
            $case,
            [
                'title' => 'Follow up on document request',
                'description' => 'Document request sent via template: ' . $event->template->name,
                'task_type' => TaskType::ProviderFollowUp->value,
                'due_date' => $this->businessDays->addDays(now(), 3)->toDateString(),
                'follow_up_date' => $this->businessDays->addDays(now(), 3)->toDateString(),
                'assigned_admin_id' => $case->assigned_admin_id,
            ],
            $event->adminId
        );
    }

    public function onCaseStatusChanged(CredentialingCase $case, ?int $oldStatusId, int $newStatusId, ?int $adminId = null): void
    {
        if ($case->do_not_automate) {
            return;
        }

        $status = Status::find($newStatusId);
        if (! $status) {
            return;
        }

        $oldStatus = $oldStatusId ? Status::find($oldStatusId) : null;

        if ($this->isApplicationSubmitted($status, $oldStatus)) {
            $this->taskService->ensureTask(
                'payer_follow_up:' . $case->id,
                $case,
                [
                    'title' => 'Payer follow-up: ' . $case->case_number,
                    'description' => 'Application submitted — follow up with payer',
                    'task_type' => TaskType::PayerFollowUp->value,
                    'due_date' => $this->businessDays->addDays(now(), 3)->toDateString(),
                    'follow_up_date' => $this->businessDays->addDays(now(), 3)->toDateString(),
                    'assigned_admin_id' => $case->assigned_admin_id,
                ],
                $adminId
            );
        }

        if ($status->dashboard_category === 'approved') {
            $this->taskService->ensureTask(
                'billing_readiness:' . $case->id,
                $case,
                [
                    'title' => 'Billing readiness: ' . $case->case_number,
                    'description' => 'Case approved — verify billing readiness',
                    'task_type' => TaskType::BillingReadiness->value,
                    'due_date' => now()->addDays(5)->toDateString(),
                    'assigned_admin_id' => $case->assigned_admin_id,
                ],
                $adminId
            );
        }
    }

    public function onProviderResponseReceived(ProviderResponseReceived $event): void
    {
        $case = $event->case;

        $this->taskService->ensureTask(
            'provider_review:' . $case->id . ':' . $event->message->id,
            $case,
            [
                'title' => 'Review provider response: ' . $case->case_number,
                'description' => 'Inbound email: ' . $event->message->subject,
                'task_type' => TaskType::Review->value,
                'due_date' => now()->addDay()->toDateString(),
                'assigned_admin_id' => $case->assigned_admin_id,
            ],
            $event->adminId
        );
    }

    public function onPayerNoResponse(CredentialingCase $case, ?int $adminId = null): void
    {
        $this->taskService->ensureTask(
            'payer_no_response:' . $case->id,
            $case,
            [
                'title' => 'Escalation — no payer response: ' . $case->case_number,
                'description' => 'Payer SLA exceeded with no inbound response',
                'task_type' => TaskType::Escalation->value,
                'due_date' => now()->addDay()->toDateString(),
                'assigned_admin_id' => $case->assigned_admin_id,
            ],
            $adminId
        );

        $case->update(['is_escalated' => true]);
    }

    public function syncExpiryTasks(): int
    {
        $count = 0;

        Document::expiringSoon(30)
            ->with(['provider', 'documentType'])
            ->each(function (Document $document) use (&$count) {
                if (! $document->provider_id) {
                    return;
                }

                $title = 'Renew: ' . ($document->documentType->name ?? $document->title ?? 'Document');

                $existing = Task::query()
                    ->where('automation_key', 'expiry:' . $document->id)
                    ->open()
                    ->exists();

                if ($existing) {
                    return;
                }

                $this->taskService->create([
                    'automation_key' => 'expiry:' . $document->id,
                    'title' => $title,
                    'description' => 'Expires on ' . $document->expiry_date->format('m/d/Y'),
                    'provider_id' => $document->provider_id,
                    'credentialing_case_id' => $document->credentialing_case_id,
                    'task_type' => TaskType::Expiry->value,
                    'due_date' => $document->expiry_date,
                ], 1);

                $count++;
            });

        return $count;
    }

    public function processPayerNoResponseCases(): int
    {
        $count = 0;

        CredentialingCase::query()
            ->active()
            ->whereHas('status', fn ($q) => $q->where('dashboard_category', 'payer'))
            ->whereDoesntHave('emailMessages', fn ($q) => $q
                ->where('direction', 'inbound')
                ->where('queue_category', 'payer_responses')
                ->where('received_at', '>=', now()->subDays(14)))
            ->where('last_action_at', '<=', now()->subDays(14))
            ->each(function (CredentialingCase $case) use (&$count) {
                $this->onPayerNoResponse($case);
                $count++;
            });

        return $count;
    }

    protected function isApplicationSubmitted(Status $status, ?Status $oldStatus): bool
    {
        if ($status->dashboard_category !== 'payer') {
            return false;
        }

        $name = strtolower($status->name);

        if (str_contains($name, 'filed') || str_contains($name, 'submitted') || str_contains($name, 'at payer')) {
            if ($oldStatus && $oldStatus->dashboard_category === 'payer') {
                return false;
            }

            return true;
        }

        return false;
    }
}
