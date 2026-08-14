<?php

namespace App\Services;

use App\Mail\CredentialingTemplateMail;
use App\Models\CaseSlaEvent;
use App\Models\CaseSlaTimer;
use App\Models\CredentialingCase;
use App\Models\DelayOwner;
use App\Models\EmailMessage;
use App\Models\NotificationTemplate;
use App\Models\SlaRule;
use App\Models\Status;
use App\Models\Task;
use App\Support\BusinessDayCalculator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SlaEngineService
{
    public function __construct(
        protected BusinessDayCalculator $businessDays,
        protected CredentialingEmailService $emailService,
    ) {}

    public function syncTimersForCase(CredentialingCase $case, Status $status): void
    {
        CaseSlaTimer::where('credentialing_case_id', $case->id)
            ->where('status', 'active')
            ->update(['status' => 'completed', 'completed_at' => now()]);

        $category = $status->dashboard_category;

        $ruleKeys = match ($category) {
            'provider' => ['provider_reminder_1', 'provider_reminder_2', 'provider_escalation'],
            'payer' => ['payer_follow_up'],
            'internal' => ['internal_review'],
            default => [],
        };

        foreach ($ruleKeys as $ruleKey) {
            $this->startTimer($case, $ruleKey);
        }
    }

    public function startTimer(CredentialingCase $case, string $ruleKey): ?CaseSlaTimer
    {
        $rule = SlaRule::where('rule_key', $ruleKey)->where('is_active', true)->first();
        if (! $rule) {
            return null;
        }

        $startedAt = now();
        $dueAt = $this->businessDays->addDays($startedAt, $rule->days, $rule->business_days_only);

        return CaseSlaTimer::create([
            'credentialing_case_id' => $case->id,
            'rule_key' => $ruleKey,
            'started_at' => $startedAt,
            'due_at' => $dueAt,
            'status' => 'active',
        ]);
    }

    public function runDueChecks(): array
    {
        $processed = ['reminders' => 0, 'escalations' => 0, 'tasks' => 0];

        $timers = CaseSlaTimer::with(['credentialingCase.provider.user', 'credentialingCase.payer'])
            ->where('status', 'active')
            ->where('due_at', '<=', now())
            ->get();

        foreach ($timers as $timer) {
            $rule = SlaRule::where('rule_key', $timer->rule_key)->where('is_active', true)->first();
            if (! $rule) {
                $timer->markCompleted();
                continue;
            }

            $case = $timer->credentialingCase;
            if (! $case || $this->caseIsClosed($case)) {
                $timer->markCompleted();
                continue;
            }

            $this->executeRuleAction($case, $rule, $timer);
            $timer->markTriggered();

            if ($rule->action === 'escalate') {
                $processed['escalations']++;
            } elseif ($rule->action === 'reminder') {
                $processed['reminders']++;
            } else {
                $processed['tasks']++;
            }
        }

        return $processed;
    }

    protected function executeRuleAction(CredentialingCase $case, SlaRule $rule, CaseSlaTimer $timer): void
    {
        CaseSlaEvent::create([
            'credentialing_case_id' => $case->id,
            'rule_key' => $rule->rule_key,
            'event_type' => $rule->action,
            'metadata' => ['timer_id' => $timer->id],
        ]);

        match ($rule->action) {
            'reminder' => $this->sendReminder($case, $rule),
            'escalate' => $this->escalateCase($case, $rule),
            'task' => $this->createFollowUpTask($case, $rule),
            'shift_delay' => $this->shiftDelayOwner($case, $rule),
            default => null,
        };
    }

    protected function sendReminder(CredentialingCase $case, SlaRule $rule): void
    {
        if (! $rule->notification_template_id) {
            return;
        }

        $providerEmail = $case->provider->user->email ?? null;
        if (! $providerEmail) {
            $case->addActivity('system', 'SLA reminder skipped — no provider email on file');
            return;
        }

        $this->emailService->sendFromTemplate(
            $case,
            $rule->notificationTemplate,
            $providerEmail,
            null,
            null
        );

        app(DelayOwnershipService::class)->shiftToDelayOwnerByName(
            $case,
            'Provider/Practice',
            'Auto-shifted after ' . $rule->name,
        );

        $case->addActivity('system', 'Auto-reminder sent: ' . $rule->name);
    }

    protected function escalateCase(CredentialingCase $case, SlaRule $rule): void
    {
        $case->update(['is_escalated' => true]);
        $case->addActivity('system', 'Case escalated — ' . $rule->name);

        Task::firstOrCreate(
            [
                'credentialing_case_id' => $case->id,
                'task_type' => 'escalation',
                'title' => 'Manager review: ' . $case->case_number,
            ],
            [
                'description' => $rule->name,
                'provider_id' => $case->provider_id,
                'assigned_admin_id' => $case->assigned_admin_id,
                'due_date' => now()->addDay(),
            ]
        );

        if ($rule->notification_template_id) {
            $managerEmail = config('credentialing.mailbox.from_address');
            $this->emailService->sendFromTemplate($case, $rule->notificationTemplate, $managerEmail);
        }
    }

    protected function createFollowUpTask(CredentialingCase $case, SlaRule $rule): void
    {
        Task::firstOrCreate(
            [
                'credentialing_case_id' => $case->id,
                'task_type' => 'sla',
                'title' => $rule->name . ': ' . ($case->payer->name ?? $case->case_number),
            ],
            [
                'provider_id' => $case->provider_id,
                'assigned_admin_id' => $case->assigned_admin_id,
                'due_date' => now()->addDays(2),
                'description' => 'Auto-generated payer follow-up task',
            ]
        );

        app(DelayOwnershipService::class)->shiftToDelayOwnerByName(
            $case,
            'Revantage Team',
            'Internal follow-up after payer SLA',
        );

        $case->addActivity('system', 'Payer follow-up task created: ' . $rule->name);
    }

    protected function shiftDelayOwner(CredentialingCase $case, SlaRule $rule): void
    {
        $ownerName = match ($rule->applies_to) {
            'provider' => 'Provider/Practice',
            'payer' => 'Payer',
            default => 'Revantage Team',
        };

        app(DelayOwnershipService::class)->shiftToDelayOwnerByName(
            $case,
            $ownerName,
            'SLA delay shift: ' . $rule->name,
        );
    }

    protected function caseIsClosed(CredentialingCase $case): bool
    {
        return $case->status && in_array($case->status->dashboard_category, ['approved', 'closed'], true);
    }

    public function startTimersForCase(CredentialingCase $case): void
    {
        $case->loadMissing('status');
        if ($case->status) {
            $this->syncTimersForCase($case, $case->status);
        }
    }

    public function processDueTimers(): array
    {
        return $this->runDueChecks();
    }
}
