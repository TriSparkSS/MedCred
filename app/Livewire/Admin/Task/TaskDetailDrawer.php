<?php

namespace App\Livewire\Admin\Task;

use App\Models\Task;
use App\Services\TaskService;
use App\Services\TaskSyncService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class TaskDetailDrawer extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public ?int $taskId = null;

    public bool $show = false;

    public string $noteBody = '';

    public string $reassignReason = '';

    public ?int $reassignAdminId = null;

    public $attachment;

    protected $listeners = ['openTaskDetail' => 'open'];

    public function open(int $taskId): void
    {
        $this->taskId = $taskId;
        $this->show = true;
        $this->reset(['noteBody', 'reassignReason', 'reassignAdminId', 'attachment']);
    }

    public function close(): void
    {
        $this->show = false;
        $this->taskId = null;
    }

    public function addNote(TaskService $taskService): void
    {
        $this->validate(['noteBody' => 'required|string|max:5000']);

        $task = Task::findOrFail($this->taskId);
        $this->authorize('update', $task);

        $taskService->addNote($task, $this->noteBody, Auth::guard('admin')->id());
        $this->noteBody = '';
        flash()->success('Note added.');
    }

    public function uploadAttachment(TaskService $taskService): void
    {
        $this->validate(['attachment' => 'required|file|max:10240']);

        $task = Task::findOrFail($this->taskId);
        $this->authorize('update', $task);

        $taskService->attachFile($task, $this->attachment, Auth::guard('admin')->id());
        $this->reset('attachment');
        flash()->success('Attachment uploaded.');
    }

    public function complete(TaskService $taskService): void
    {
        $task = Task::findOrFail($this->taskId);
        $this->authorize('update', $task);
        $taskService->complete($task, Auth::guard('admin')->id());
        flash()->success('Task completed.');
        $this->dispatch('taskUpdated');
    }

    public function reopen(TaskService $taskService): void
    {
        $task = Task::findOrFail($this->taskId);
        $this->authorize('reopen', $task);
        $taskService->reopen($task, Auth::guard('admin')->id());
        flash()->success('Task reopened.');
        $this->dispatch('taskUpdated');
    }

    public function escalate(TaskService $taskService): void
    {
        $task = Task::findOrFail($this->taskId);
        $this->authorize('escalate', $task);
        $taskService->escalate($task, Auth::guard('admin')->id());
        flash()->success('Task escalated.');
        $this->dispatch('taskUpdated');
    }

    public function reassign(TaskService $taskService): void
    {
        $task = Task::findOrFail($this->taskId);
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

    public function render(TaskSyncService $taskSync)
    {
        $task = $this->taskId
            ? Task::with([
                'assignedAdmin',
                'createdByAdmin',
                'provider.user',
                'credentialingCase.payer',
                'priority',
                'notes.admin',
                'attachments.uploadedByAdmin',
                'activities.admin',
            ])->find($this->taskId)
            : null;

        return view('livewire.admin.task.task-detail-drawer', [
            'task' => $task,
            'admins' => \App\Models\Admin::assignable()->get(['id', 'name', 'username']),
            'taskTypeLabel' => $task ? $taskSync->taskTypeLabel($task->task_type) : '',
        ]);
    }
}
