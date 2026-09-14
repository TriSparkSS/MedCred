<?php

namespace App\Livewire\Admin\Credential;

use App\Events\DocumentRequestSent;
use App\Models\Admin;
use App\Models\CredentialingCase;
use App\Models\DelayOwner;
use App\Models\NotificationTemplate;
use App\Models\Status;
use App\Models\Task;
use App\Services\AdminScopeService;
use App\Services\BillingReadinessService;
use App\Services\CredentialingCaseService;
use App\Services\CredentialingEmailService;
use App\Services\DelayOwnershipService;
use App\Services\TaskService;
use App\Services\TaskSyncService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'Credentialing Case'])]
class CredentialDetailsPage extends Component
{
    use AuthorizesRequests;

    public CredentialingCase $case;

    #[Url(as: 'tab', history: true)]
    public string $activeTab = 'details';

    public $newNote = '';

    public $statusId = '';

    public $delayOwnerId = '';

    public $overrideReason = '';

    public $emailTemplateId = '';

    public $newTaskTitle = '';

    public $newTaskDueDate = '';

    public $newTaskAssigneeId = '';

    public bool $newTaskRepeat = false;

    public int $newTaskIntervalDays = 7;

    public int $newTaskOccurrences = 3;

    /** @var list<int|string> */
    public array $selectedTaskIds = [];

    public string $bulkAssigneeId = '';

    public array $billingForm = [];

    /** @var list<string> */
    protected array $allowedTabs = [
        'details',
        'documents',
        'communication',
        'tasks',
        'billing',
        'timeline',
    ];

    public function mount(CredentialingCase $case, AdminScopeService $scope): void
    {
        $admin = Auth::guard('admin')->user();
        if ($admin && ! $scope->canAccessCase($admin, $case)) {
            abort(403, 'You do not have access to this case.');
        }

        $this->case = $case;
        $this->statusId = $case->status_id;
        $this->delayOwnerId = $case->delay_owner_id ?? '';
        $this->newTaskDueDate = now()->addDays(3)->toDateString();
        $this->newTaskAssigneeId = $case->assigned_admin_id ?? Auth::guard('admin')->id();

        if (! in_array($this->activeTab, $this->allowedTabs, true)) {
            $this->activeTab = 'details';
        }

        if ($this->activeTab === 'billing') {
            $this->loadBillingForm();
        }
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = in_array($tab, $this->allowedTabs, true) ? $tab : 'details';
        if ($this->activeTab === 'billing') {
            $this->loadBillingForm();
        }
    }

    public function saveStatus(CredentialingCaseService $caseService): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.credentials.edit'), 403);

        if (! $this->statusId) {
            return;
        }

        $this->case->refresh();
        if ($this->case->status_id != $this->statusId) {
            $caseService->changeStatus($this->case, (int) $this->statusId, Auth::guard('admin')->id());
            $this->case->refresh();
        }

        flash()->success('Case updated.');
    }

    public function addNote(): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.credentials.edit'), 403);

        $this->validate(['newNote' => 'required|string|max:2000']);

        $this->case->addActivity('note', $this->newNote, Auth::guard('admin')->id());
        $this->newNote = '';
        flash()->success('Note added.');
    }

    public function saveDelayOverride(DelayOwnershipService $delayService): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.delay.override'), 403);

        $this->validate([
            'delayOwnerId' => 'required|exists:delay_owners,id',
            'overrideReason' => 'required|string|max:500',
        ]);

        $delayService->overrideDelayOwner(
            $this->case,
            (int) $this->delayOwnerId,
            $this->overrideReason,
            Auth::guard('admin')->id()
        );

        $this->overrideReason = '';
        $this->case->refresh();
        flash()->success('Delay owner updated.');
    }

    public function sendDocumentRequest(CredentialingEmailService $emailService): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.emails.send'), 403);

        $this->validate(['emailTemplateId' => 'required|exists:notification_templates,id']);

        $this->case->loadMissing('provider.user');
        $template = NotificationTemplate::findOrFail($this->emailTemplateId);
        $to = $this->case->provider->user->email ?? null;

        if (! $to) {
            flash()->error('Provider has no email address on file.');

            return;
        }

        $emailService->sendFromTemplate($this->case, $template, $to, Auth::guard('admin')->id());
        DocumentRequestSent::dispatch($this->case, $template, Auth::guard('admin')->id());
        flash()->success('Email sent to provider.');
    }

    public function toggleEscalation(): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.tasks.escalate'), 403);

        $this->case->update(['is_escalated' => ! $this->case->is_escalated]);
        $this->case->refresh();
        $this->case->addActivity(
            'system',
            $this->case->is_escalated ? 'Case escalated' : 'Escalation removed',
            Auth::guard('admin')->id()
        );
        flash()->success($this->case->is_escalated ? 'Case escalated.' : 'Escalation removed.');
    }

    public function createFollowUpTask(TaskService $taskService): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.tasks.manage'), 403);

        $this->validate([
            'newTaskTitle' => 'required|string|max:255',
            'newTaskDueDate' => 'nullable|date',
            'newTaskAssigneeId' => 'nullable|exists:admins,id',
            'newTaskRepeat' => 'boolean',
            'newTaskIntervalDays' => 'required_if:newTaskRepeat,true|integer|min:1|max:90',
            'newTaskOccurrences' => 'required_if:newTaskRepeat,true|integer|min:2|max:12',
        ]);

        $occurrences = $this->newTaskRepeat ? $this->newTaskOccurrences : 1;
        $intervalDays = $this->newTaskRepeat ? $this->newTaskIntervalDays : 0;

        $count = $taskService->createFollowUpsForCases(
            [$this->case->id],
            [
                'title' => $this->newTaskTitle,
                'description' => 'Follow-up for '.$this->case->case_number,
                'assigned_admin_id' => $this->newTaskAssigneeId ?: null,
                'due_date' => $this->newTaskDueDate ?: now()->toDateString(),
                'task_type' => 'follow_up',
                'occurrences' => $occurrences,
                'interval_days' => $intervalDays,
                'sync_case_follow_up' => false,
            ],
            Auth::guard('admin')->id()
        );

        $this->newTaskTitle = '';
        $this->newTaskDueDate = now()->addDays(3)->toDateString();
        $this->newTaskRepeat = false;
        $this->newTaskOccurrences = 3;
        $this->newTaskIntervalDays = 7;
        flash()->success($count === 1 ? 'Follow-up task created.' : "Created {$count} follow-up tasks.");
    }

    public function completeTask(int $taskId, TaskService $taskService): void
    {
        $task = Task::where('credentialing_case_id', $this->case->id)->findOrFail($taskId);
        $this->authorize('update', $task);
        $taskService->complete($task, Auth::guard('admin')->id());
    }

    public function bulkAssignSelected(TaskService $taskService): void
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin?->can('admin.tasks.assign'), 403);

        $count = $taskService->bulkAssign($this->selectedCaseTasks(), $this->bulkAssigneeId !== '' ? (int) $this->bulkAssigneeId : null, $admin->id);
        $this->clearTaskSelection();
        flash()->success($count === 1 ? 'Assigned 1 task.' : "Assigned {$count} tasks.");
    }

    public function bulkCompleteSelected(TaskService $taskService): void
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin?->can('admin.tasks.manage'), 403);

        $count = $taskService->bulkUpdateStatus($this->selectedCaseTasks(), 'completed', $admin->id, $admin);
        $this->clearTaskSelection();
        flash()->success($count === 1 ? 'Completed 1 task.' : "Completed {$count} tasks.");
    }

    public function bulkCancelSelected(TaskService $taskService): void
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin?->can('admin.tasks.manage'), 403);

        $count = $taskService->bulkUpdateStatus($this->selectedCaseTasks(), 'cancelled', $admin->id, $admin);
        $this->clearTaskSelection();
        flash()->success($count === 1 ? 'Cancelled 1 task.' : "Cancelled {$count} tasks.");
    }

    public function clearTaskSelection(): void
    {
        $this->selectedTaskIds = [];
        $this->bulkAssigneeId = '';
    }

    protected function selectedCaseTasks()
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $this->selectedTaskIds))));

        if ($ids === []) {
            return collect();
        }

        return Task::query()
            ->where('credentialing_case_id', $this->case->id)
            ->whereIn('id', $ids)
            ->get();
    }

    public function loadBillingForm(): void
    {
        $this->case->refresh();
        $this->billingForm = [
            'approval_date' => $this->case->approval_date?->format('Y-m-d'),
            'effective_date' => $this->case->effective_date?->format('Y-m-d'),
            'payer_provider_id' => $this->case->payer_provider_id,
            'payer_group_id' => $this->case->payer_group_id,
            'eft_status' => $this->case->eft_status,
            'era_status' => $this->case->era_status,
            'billing_notes' => $this->case->billing_notes,
            'ready_to_bill' => $this->case->ready_to_bill,
            'billing_notified' => $this->case->billing_notified,
        ];
    }

    public function saveBillingFields(BillingReadinessService $billing): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.credentials.edit'), 403);

        $this->validate([
            'billingForm.approval_date' => 'nullable|date',
            'billingForm.effective_date' => 'nullable|date',
            'billingForm.payer_provider_id' => 'nullable|string|max:100',
            'billingForm.payer_group_id' => 'nullable|string|max:100',
            'billingForm.eft_status' => 'nullable|string|max:50',
            'billingForm.era_status' => 'nullable|string|max:50',
            'billingForm.billing_notes' => 'nullable|string|max:2000',
        ]);

        $billing->updateBillingFields($this->case, $this->billingForm, Auth::guard('admin')->id());
        $this->case->refresh();
        $this->loadBillingForm();
        flash()->success('Billing fields saved.');
    }

    public function notifyBilling(BillingReadinessService $billing): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.billing.notify'), 403);

        try {
            $billing->notifyBilling($this->case->fresh(), Auth::guard('admin')->id());
            $this->case->refresh();
            flash()->success('Billing team notified.');
        } catch (\InvalidArgumentException $e) {
            flash()->error($e->getMessage());
        }
    }

    public function render(TaskSyncService $taskSync, BillingReadinessService $billing)
    {
        $admin = Auth::guard('admin')->user();

        $this->case->load([
            'provider.user', 'payer', 'practice', 'location', 'status', 'delayOwner',
            'assignedAdmin', 'priority', 'caseType',
            'statusHistories.status', 'statusHistories.changedByAdmin',
            'activities.admin', 'activities.user',
            'documentItems.documentType', 'documentItems.document.versions',
            'tasks' => fn ($q) => $q->with('assignedAdmin')->orderByRaw('completed_at IS NOT NULL')->orderBy('due_date'),
            'emailCaseLinks' => fn ($q) => $q->latest()->limit(20),
            'slaTimers' => fn ($q) => $q->where('status', 'active'),
        ]);

        return view('livewire.admin.credential.credential-details-page', [
            'statuses' => Status::where('is_active', true)->orderBy('sort_order')->get(),
            'delayOwners' => DelayOwner::where('is_active', true)->orderBy('name')->get(),
            'emailTemplates' => NotificationTemplate::where('is_active', true)->orderBy('name')->get(),
            'admins' => Admin::assignable()->get(['id', 'name', 'username']),
            'taskTypeLabel' => fn (string $type) => $taskSync->taskTypeLabel($type),
            'billingService' => $billing,
            'canEditCredentials' => $admin?->can('admin.credentials.edit') ?? false,
            'canOverrideDelay' => $admin?->can('admin.delay.override') ?? false,
            'canSendEmails' => $admin?->can('admin.emails.send') ?? false,
            'canManageTasks' => $admin?->can('admin.tasks.manage') ?? false,
            'canAssignTasks' => $admin?->can('admin.tasks.assign') ?? false,
            'canEscalateTasks' => $admin?->can('admin.tasks.escalate') ?? false,
            'canNotifyBilling' => $admin?->can('admin.billing.notify') ?? false,
        ]);
    }
}
