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
    ) {}

    public function onDocumentRequestSent(DocumentRequestSent $event): void
    {
        $case = $event->case->fresh(['provider', 'assignedAdmin']);

        $this->taskService->ensureTask(
            'document_request:'.$case->id,
            $case,
            [
                'title' => 'Follow up on document request',
                'description' => 'Document request sent via template: '.$event->template->name,
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
        if ($case->do_not_automate || $case->automation_paused) {
            return;
        }

        $status = Status::find($newStatusId);
        if (! $status) {
            return;
        }

        $oldStatus = $oldStatusId ? Status::find($oldStatusId) : null;

        if ($this->isApplicationSubmitted($status, $oldStatus)) {
            $this->taskService->ensureTask(
                'payer_follow_up:'.$case->id,
                $case,
                [
                    'title' => 'Payer follow-up: '.$case->case_number,
                    'description' => 'Application submitted — follow up with payer',
                    'task_type' => TaskType::PayerFollowUp->value,
                    'due_date' => $this->businessDays->addDays(now(), 3)->toDateString(),
                    'follow_up_date' => $this->businessDays->addDays(now(), 3)->toDateString(),
                    'assigned_admin_id' => $case->assigned_admin_id,
                ],
                $adminId
            );
        }

        if ($this->isResubmissionRequired($status)) {
            $this->taskService->ensureTask(
                'resubmission:'.$case->id,
                $case,
                [
                    'title' => 'Resubmit application: '.$case->case_number,
                    'description' => 'Application returned or requires resubmission ('.$status->name.')',
                    'task_type' => TaskType::FollowUp->value,
                    'due_date' => $this->businessDays->addDays(now(), 3)->toDateString(),
                    'follow_up_date' => $this->businessDays->addDays(now(), 3)->toDateString(),
                    'assigned_admin_id' => $case->assigned_admin_id,
                ],
                $adminId
            );
        }

        if ($this->isRevalidationRequired($status)) {
            $dueDate = $case->revalidation_due_date?->toDateString()
                ?? $this->businessDays->addDays(now(), 10)->toDateString();

            $this->taskService->ensureTask(
                'revalidation:'.$case->id,
                $case,
                [
                    'title' => 'Revalidation required: '.$case->case_number,
                    'description' => 'Prepare and submit payer revalidation / recredentialing',
                    'task_type' => TaskType::Renewal->value,
                    'due_date' => $dueDate,
                    'follow_up_date' => $dueDate,
                    'assigned_admin_id' => $case->assigned_admin_id,
                ],
                $adminId
            );
        }

        if ($status->dashboard_category === 'approved') {
            $this->taskService->ensureTask(
                'billing_readiness:'.$case->id,
                $case,
                [
                    'title' => 'Billing readiness: '.$case->case_number,
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
            'provider_review:'.$case->id.':'.$event->message->id,
            $case,
            [
                'title' => 'Review provider response: '.$case->case_number,
                'description' => 'Inbound email: '.$event->message->subject,
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
            'payer_no_response:'.$case->id,
            $case,
            [
                'title' => 'Escalation — no payer response: '.$case->case_number,
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

                $title = 'Renew: '.($document->documentType->name ?? $document->title ?? 'Document');

                $existing = Task::query()
                    ->where('automation_key', 'expiry:'.$document->id)
                    ->exists();

                if ($existing) {
                    return;
                }

                $this->taskService->create([
                    'automation_key' => 'expiry:'.$document->id,
                    'title' => $title,
                    'description' => 'Expires on '.$document->expiry_date->format('m/d/Y'),
                    'provider_id' => $document->provider_id,
                    'credentialing_case_id' => $document->credentialing_case_id,
                    'task_type' => TaskType::Expiry->value,
                    'due_date' => $document->expiry_date,
                ], 1);

                $count++;
            });

        return $count;
    }

    public function syncRevalidationTasks(int $withinDays = 90): int
    {
        $count = 0;

        CredentialingCase::query()
            ->where('do_not_automate', false)
            ->where('automation_paused', false)
            ->whereBetween('revalidation_due_date', [now()->toDateString(), now()->addDays($withinDays)->toDateString()])
            ->each(function (CredentialingCase $case) use (&$count) {
                $existing = Task::query()
                    ->where('automation_key', 'revalidation:'.$case->id)
                    ->exists();

                if ($existing) {
                    return;
                }

                $this->taskService->create([
                    'automation_key' => 'revalidation:'.$case->id,
                    'title' => 'Revalidation required: '.$case->case_number,
                    'description' => 'Revalidation due on '.$case->revalidation_due_date->format('m/d/Y'),
                    'provider_id' => $case->provider_id,
                    'payer_id' => $case->payer_id,
                    'credentialing_case_id' => $case->id,
                    'assigned_admin_id' => $case->assigned_admin_id,
                    'task_type' => TaskType::Renewal->value,
                    'due_date' => $case->revalidation_due_date,
                    'follow_up_date' => $case->revalidation_due_date,
                ], $case->assigned_admin_id ?? 1);

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
            ->whereDoesntHave('activities', fn ($q) => $q
                ->whereIn('activity_type', ['email', 'email_sent', 'email_received'])
                ->where('created_at', '>=', now()->subDays(14)))
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

    protected function isResubmissionRequired(Status $status): bool
    {
        $name = strtolower($status->name);

        return str_contains($name, 'resubmit')
            || str_contains($name, 'resubmission')
            || str_contains($name, 'returned')
            || str_contains($name, 'additional info');
    }

    protected function isRevalidationRequired(Status $status): bool
    {
        if ($status->dashboard_category === 'revalidation') {
            return true;
        }

        $name = strtolower($status->name);

        return str_contains($name, 'revalidation') || str_contains($name, 'recredential');
    }
}
