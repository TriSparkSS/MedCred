<?php

namespace App\Services;

use App\Enums\AdminRole;
use App\Enums\TaskType;
use App\Models\Admin;
use App\Models\CaseSlaEvent;
use App\Models\CaseSlaTimer;
use App\Models\CredentialingCase;
use App\Models\DelayOwner;
use App\Models\SlaRule;
use App\Models\Status;
use App\Support\BusinessDayCalculator;

class SlaEngineService
{
    public function __construct(
        protected BusinessDayCalculator $businessDays,
        protected CredentialingEmailService $emailService,
    ) {}

    public function resolveRuleForCase(CredentialingCase $case, string $ruleKey): ?SlaRule
    {
        $candidates = SlaRule::where('rule_key', $ruleKey)
            ->where(function ($q) use ($case) {
                $q->whereNull('payer_id');
                if ($case->payer_id) {
                    $q->orWhere('payer_id', $case->payer_id);
                }
            })
            ->where(function ($q) use ($case) {
                $q->whereNull('practice_id');
                if ($case->practice_id) {
                    $q->orWhere('practice_id', $case->practice_id);
                }
            })
            ->where(function ($q) use ($case) {
                $q->whereNull('case_type_id');
                if ($case->case_type_id) {
                    $q->orWhere('case_type_id', $case->case_type_id);
                }
            })
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $best = $candidates->sort(function (SlaRule $a, SlaRule $b): int {
            $specA = ($a->payer_id !== null ? 1 : 0) + ($a->practice_id !== null ? 1 : 0) + ($a->case_type_id !== null ? 1 : 0);
            $specB = ($b->payer_id !== null ? 1 : 0) + ($b->practice_id !== null ? 1 : 0) + ($b->case_type_id !== null ? 1 : 0);

            if ($specA !== $specB) {
                return $specB <=> $specA;
            }

            $weightA = ($a->payer_id !== null ? 4 : 0) + ($a->practice_id !== null ? 2 : 0) + ($a->case_type_id !== null ? 1 : 0);
            $weightB = ($b->payer_id !== null ? 4 : 0) + ($b->practice_id !== null ? 2 : 0) + ($b->case_type_id !== null ? 1 : 0);

            if ($weightA !== $weightB) {
                return $weightB <=> $weightA;
            }

            return $b->id <=> $a->id;
        })->first();

        if (! $best || ! $best->is_active) {
            return null;
        }

        return $best;
    }

    public function syncTimersForCase(CredentialingCase $case, Status $status): void
    {
        CaseSlaTimer::where('credentialing_case_id', $case->id)
            ->where('status', 'active')
            ->update(['status' => 'completed', 'completed_at' => now()]);

        if ($case->automation_paused || $case->do_not_automate || $this->caseIsClosed($case)) {
            return;
        }

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
        if ($case->automation_paused || $case->do_not_automate || $this->caseIsClosed($case)) {
            return null;
        }

        $rule = $this->resolveRuleForCase($case, $ruleKey);
        if (! $rule) {
            return null;
        }

        $startedAt = now();
        $days = (int) ($rule->business_days ?? $rule->days ?? 3);
        $businessDaysOnly = (bool) ($rule->business_days_only ?? true);
        $dueAt = $this->businessDays->addDays($startedAt, $days, $businessDaysOnly);

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

        $timers = CaseSlaTimer::with([
            'credentialingCase.status',
            'credentialingCase.delayOwner',
            'credentialingCase.delayOverrides',
            'credentialingCase.assignedAdmin',
            'credentialingCase.provider.user',
            'credentialingCase.payer',
        ])
            ->where('status', 'active')
            ->where('due_at', '<=', now())
            ->get();

        foreach ($timers as $timer) {
            $case = $timer->credentialingCase;
            if (! $case || $this->caseIsClosed($case) || $case->automation_paused || $case->do_not_automate) {
                $timer->markCompleted();

                continue;
            }

            $rule = $this->resolveRuleForCase($case, $timer->rule_key);
            if (! $rule) {
                $timer->markCompleted();

                continue;
            }

            if ($this->hasConflictingDelayOverride($case, $rule)) {
                $timer->markCompleted();

                continue;
            }

            if ($rule->action === 'reminder' && $this->hasDuplicateReminderEvent($case, $rule, $timer)) {
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
        if ($rule->action === 'reminder') {
            $this->sendReminder($case, $rule, $timer);

            return;
        }

        CaseSlaEvent::create([
            'credentialing_case_id' => $case->id,
            'rule_key' => $rule->rule_key,
            'event_type' => $rule->action,
            'metadata' => ['timer_id' => $timer->id],
        ]);

        match ($rule->action) {
            'escalate' => $this->escalateCase($case, $rule),
            'task' => $this->createFollowUpTask($case, $rule),
            'shift_delay', 'shift_delay_owner' => $this->shiftDelayOwner($case, $rule),
            default => null,
        };
    }

    protected function sendReminder(CredentialingCase $case, SlaRule $rule, ?CaseSlaTimer $timer = null): void
    {
        if ($case->automation_paused || $case->do_not_automate || $this->caseIsClosed($case)) {
            return;
        }

        if ($this->hasConflictingDelayOverride($case, $rule)) {
            return;
        }

        if ($this->hasDuplicateReminderEvent($case, $rule, $timer)) {
            return;
        }

        CaseSlaEvent::create([
            'credentialing_case_id' => $case->id,
            'rule_key' => $rule->rule_key,
            'event_type' => 'reminder',
            'metadata' => $timer ? ['timer_id' => $timer->id] : [],
        ]);

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
            'Auto-shifted after '.$rule->name,
        );

        $case->addActivity('system', 'Auto-reminder sent: '.$rule->name);
    }

    protected function hasDuplicateReminderEvent(CredentialingCase $case, SlaRule $rule, ?CaseSlaTimer $timer = null): bool
    {
        $recentExists = CaseSlaEvent::where('credentialing_case_id', $case->id)
            ->where('rule_key', $rule->rule_key)
            ->where('event_type', 'reminder')
            ->where('created_at', '>=', now()->subHours(24))
            ->exists();

        if ($recentExists) {
            return true;
        }

        if ($timer) {
            $events = CaseSlaEvent::where('credentialing_case_id', $case->id)
                ->where('rule_key', $rule->rule_key)
                ->where('event_type', 'reminder')
                ->get();

            foreach ($events as $event) {
                if (($event->metadata['timer_id'] ?? null) == $timer->id) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function hasConflictingDelayOverride(CredentialingCase $case, SlaRule $rule): bool
    {
        $hasManualOverride = filled($case->delay_override_reason ?? null)
            || ($case->relationLoaded('delayOverrides') ? $case->delayOverrides->isNotEmpty() : $case->delayOverrides()->exists());

        if (! $hasManualOverride) {
            return false;
        }

        $ruleCategory = $rule->dashboard_category ?: $this->inferCategoryFromRuleKey($rule->rule_key);
        if (! $ruleCategory) {
            return false;
        }

        $case->loadMissing('delayOwner');
        $ownerCategory = $this->categoryForDelayOwner($case->delayOwner);

        return $ownerCategory !== $ruleCategory;
    }

    protected function inferCategoryFromRuleKey(string $ruleKey): ?string
    {
        $lower = strtolower($ruleKey);
        if (str_starts_with($lower, 'provider')) {
            return 'provider';
        }
        if (str_starts_with($lower, 'payer')) {
            return 'payer';
        }
        if (str_starts_with($lower, 'internal')) {
            return 'internal';
        }

        return null;
    }

    protected function categoryForDelayOwner(?DelayOwner $owner): ?string
    {
        if (! $owner || blank($owner->name)) {
            return null;
        }

        $name = strtolower($owner->name);
        if (str_contains($name, 'provider') || str_contains($name, 'practice')) {
            return 'provider';
        }
        if (str_contains($name, 'payer')) {
            return 'payer';
        }
        if (str_contains($name, 'revantage') || str_contains($name, 'internal') || str_contains($name, 'team')) {
            return 'internal';
        }

        return null;
    }

    protected function escalateCase(CredentialingCase $case, SlaRule $rule): void
    {
        $case->update(['is_escalated' => true]);
        $case->addActivity('system', 'Case escalated — '.$rule->name);

        app(TaskService::class)->ensureTask(
            'sla_escalation:'.$case->id.':'.$rule->rule_key,
            $case,
            [
                'task_type' => TaskType::Escalation->value,
                'title' => 'Manager review: '.$case->case_number,
                'description' => $rule->name,
                'assigned_admin_id' => $case->assigned_admin_id,
                'due_date' => now()->addDay()->toDateString(),
            ]
        );

        if ($rule->notification_template_id && $rule->notificationTemplate) {
            $recipientEmails = collect();
            try {
                $recipientEmails = Admin::role([
                    AdminRole::CredentialingManager->value,
                    AdminRole::SystemAdmin->value,
                ])->where('status', 'active')->pluck('email')->filter()->unique()->values();
            } catch (\Throwable) {
                $recipientEmails = collect();
            }

            if ($recipientEmails->isEmpty()) {
                $fallback = $case->assignedAdmin?->email ?: config('credentialing.mailbox.from_address');
                if ($fallback) {
                    $recipientEmails = collect([$fallback]);
                }
            }

            foreach ($recipientEmails as $recipientEmail) {
                try {
                    $this->emailService->sendFromTemplate($case, $rule->notificationTemplate, $recipientEmail);
                } catch (\Throwable) {
                    // Prevent mail transport failures from halting SLA processing
                }
            }
        }
    }

    protected function createFollowUpTask(CredentialingCase $case, SlaRule $rule): void
    {
        app(TaskService::class)->ensureTask(
            'sla_task:'.$case->id.':'.$rule->rule_key,
            $case,
            [
                'task_type' => TaskType::Sla->value,
                'title' => $rule->name.': '.($case->payer->name ?? $case->case_number),
                'assigned_admin_id' => $case->assigned_admin_id,
                'due_date' => now()->addDays(2)->toDateString(),
                'description' => 'Auto-generated payer follow-up task',
            ]
        );

        app(DelayOwnershipService::class)->shiftToDelayOwnerByName(
            $case,
            'Revantage Team',
            'Internal follow-up after payer SLA',
        );

        $case->addActivity('system', 'Payer follow-up task created: '.$rule->name);
    }

    protected function shiftDelayOwner(CredentialingCase $case, SlaRule $rule): void
    {
        $category = $rule->dashboard_category ?? $rule->applies_to;
        $ownerName = match ($category) {
            'provider' => 'Provider/Practice',
            'payer' => 'Payer',
            default => 'Revantage Team',
        };

        app(DelayOwnershipService::class)->shiftToDelayOwnerByName(
            $case,
            $ownerName,
            'SLA delay shift: '.$rule->name,
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
