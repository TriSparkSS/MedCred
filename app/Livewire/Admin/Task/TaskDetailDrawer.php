<?php

namespace App\Livewire\Admin\Task;

use App\Models\NotificationTemplate;
use App\Models\Task;
use App\Services\AdminScopeService;
use App\Services\CredentialingEmailService;
use App\Services\TaskService;
use App\Services\TaskSyncService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class TaskDetailDrawer extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    #[Locked]
    public ?int $taskId = null;

    public bool $show = false;

    public string $noteBody = '';

    public string $reassignReason = '';

    public ?int $reassignAdminId = null;

    public $attachment;

    public bool $showEmailForm = false;

    public string $emailTemplateId = '';

    public string $emailTo = '';

    public string $emailSubject = '';

    public string $emailBody = '';

    protected $listeners = ['openTaskDetail' => 'open'];

    public function open(int $taskId): void
    {
        $task = $this->findAuthorizedTask($taskId);
        abort_if(! $task, 403);

        $this->taskId = $task->id;
        $this->show = true;
        $this->reset([
            'noteBody',
            'reassignReason',
            'reassignAdminId',
            'attachment',
            'showEmailForm',
            'emailTemplateId',
            'emailTo',
            'emailSubject',
            'emailBody',
        ]);
    }

    public function close(): void
    {
        $this->show = false;
        $this->taskId = null;
    }

    public function toggleEmailForm(): void
    {
        $this->showEmailForm = ! $this->showEmailForm;

        if ($this->showEmailForm) {
            $task = $this->findAuthorizedTask($this->taskId);
            if ($task) {
                if ($this->emailTo === '') {
                    $this->emailTo = (string) ($task->provider?->user?->email
                        ?? $task->credentialingCase?->provider?->user?->email
                        ?? $task->credentialingCase?->payer?->email
                        ?? '');
                }
                if ($this->emailSubject === '') {
                    $this->emailSubject = 'Regarding Task: '.$task->title;
                }
            }
        }
    }

    public function updatedEmailTemplateId($value): void
    {
        if (! $value) {
            return;
        }

        $template = NotificationTemplate::find($value);
        if (! $template) {
            return;
        }

        $task = $this->findAuthorizedTask($this->taskId);
        $variables = [
            'case_number' => $task?->credentialingCase?->case_number ?? '',
            'provider_name' => $task?->provider?->user?->name ?? $task?->credentialingCase?->provider?->user?->name ?? '',
            'payer_name' => $task?->credentialingCase?->payer?->name ?? '',
            'practice_name' => $task?->credentialingCase?->practice?->legal_name ?? '',
            'task_title' => $task?->title ?? '',
        ];

        $rendered = $template->render($variables);
        $this->emailSubject = $rendered['subject'];
        $this->emailBody = $rendered['body'];
    }

    public function sendTaskEmail(CredentialingEmailService $emailService, TaskService $taskService): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.emails.send'), 403);

        $task = $this->findAuthorizedTask($this->taskId);
        abort_if(! $task, 403);

        $this->validate([
            'emailTo' => 'required|email|max:255',
            'emailSubject' => 'required|string|max:255',
            'emailBody' => 'required|string|max:10000',
        ]);

        $adminId = Auth::guard('admin')->id();
        $template = $this->emailTemplateId !== '' ? NotificationTemplate::find($this->emailTemplateId) : null;

        $result = $emailService->send(
            $task->credentialingCase,
            $this->emailTo,
            $this->emailSubject,
            $this->emailBody,
            $adminId,
            $template
        );

        if (! $result->success) {
            flash()->error($result->errorMessage ?: 'Failed to send email.');

            return;
        }

        $taskService->addNote($task, "Email sent to {$this->emailTo}: {$this->emailSubject}", $adminId);
        $this->reset(['showEmailForm', 'emailTemplateId', 'emailTo', 'emailSubject', 'emailBody']);
        flash()->success('Email sent from task.');
    }

    public function addNote(TaskService $taskService): void
    {
        $this->validate(['noteBody' => 'required|string|max:5000']);

        $task = $this->findAuthorizedTask($this->taskId);
        abort_if(! $task, 403);
        $this->authorize('update', $task);

        $taskService->addNote($task, $this->noteBody, Auth::guard('admin')->id());
        $this->noteBody = '';
        flash()->success('Note added.');
    }

    public function uploadAttachment(TaskService $taskService): void
    {
        $this->validate(['attachment' => 'required|file|max:10240']);

        $task = $this->findAuthorizedTask($this->taskId);
        abort_if(! $task, 403);
        $this->authorize('update', $task);

        $taskService->attachFile($task, $this->attachment, Auth::guard('admin')->id());
        $this->reset('attachment');
        flash()->success('Attachment uploaded.');
    }

    public function complete(TaskService $taskService): void
    {
        $task = $this->findAuthorizedTask($this->taskId);
        abort_if(! $task, 403);
        $this->authorize('update', $task);
        $taskService->complete($task, Auth::guard('admin')->id());
        flash()->success('Task completed.');
        $this->dispatch('taskUpdated');
    }

    public function reopen(TaskService $taskService): void
    {
        $task = $this->findAuthorizedTask($this->taskId);
        abort_if(! $task, 403);
        $this->authorize('reopen', $task);
        $taskService->reopen($task, Auth::guard('admin')->id());
        flash()->success('Task reopened.');
        $this->dispatch('taskUpdated');
    }

    public function escalate(TaskService $taskService): void
    {
        $task = $this->findAuthorizedTask($this->taskId);
        abort_if(! $task, 403);
        $this->authorize('escalate', $task);
        $taskService->escalate($task, Auth::guard('admin')->id());
        flash()->success('Task escalated.');
        $this->dispatch('taskUpdated');
    }

    public function reassign(TaskService $taskService): void
    {
        $task = $this->findAuthorizedTask($this->taskId);
        abort_if(! $task, 403);
        $this->authorize('assign', $task);

        $taskService->reassign(
            $task,
            $this->reassignAdminId ?: null,
            Auth::guard('admin')->id(),
            $this->reassignReason
        );

        $this->reset(['reassignReason', 'reassignAdminId']);
        flash()->success('Task reassigned.');
        $this->dispatch('taskUpdated');
    }

    protected function findAuthorizedTask(?int $id): ?Task
    {
        if (! $id) {
            return null;
        }

        $admin = Auth::guard('admin')->user();
        if (! $admin || ! $admin->can('admin.tasks.view')) {
            return null;
        }

        $query = Task::with([
            'assignedAdmin',
            'createdByAdmin',
            'provider.user',
            'provider.practices',
            'credentialingCase.payer',
            'credentialingCase.practice',
            'credentialingCase.provider.user',
            'priority',
            'notes.admin',
            'attachments.uploadedByAdmin',
            'activities.admin',
        ]);

        app(AdminScopeService::class)->scopeTasks($query, $admin);

        return $query->find($id);
    }

    public function render(TaskSyncService $taskSync)
    {
        $task = $this->findAuthorizedTask($this->taskId);

        return view('livewire.admin.task.task-detail-drawer', [
            'task' => $task,
            'admins' => \App\Models\Admin::assignable()->get(['id', 'name', 'username']),
            'emailTemplates' => NotificationTemplate::where('is_active', true)->orderBy('name')->get(),
            'canSendEmails' => Auth::guard('admin')->user()?->can('admin.emails.send') ?? false,
            'taskTypeLabel' => $task ? $taskSync->taskTypeLabel($task->task_type) : '',
        ]);
    }
}
