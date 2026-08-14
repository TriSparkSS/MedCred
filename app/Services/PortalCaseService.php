<?php

namespace App\Services;

use App\Enums\TaskType;
use App\Models\CredentialingCase;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\Task;
use Illuminate\Support\Collection;

class PortalCaseService
{
    public function __construct(
        protected PortalActionItemsService $actionItems,
    ) {}

    public function findProviderCase(ProviderDetails $provider, int|string $caseId): CredentialingCase
    {
        $case = CredentialingCase::where('provider_id', $provider->id)->find($caseId);

        abort_unless($case, 404);

        return $this->loadCaseRelations($case);
    }

    public function findPracticeCase(Practice $practice, int|string $caseId): CredentialingCase
    {
        $case = CredentialingCase::where('practice_id', $practice->id)->find($caseId);

        abort_unless($case, 404);

        return $this->loadCaseRelations($case);
    }

    public function caseDetailPayload(CredentialingCase $case): array
    {
        $tasks = $this->casePortalTasks($case);
        $groupedTasks = $this->actionItems->groupTasksForKanban($tasks);

        return [
            'case' => $case,
            'checklist' => $case->checklist_completion,
            'tasks' => $tasks,
            'grouped_tasks' => $groupedTasks,
        ];
    }

    public function casePortalTasks(CredentialingCase $case): Collection
    {
        return Task::query()
            ->where('credentialing_case_id', $case->id)
            ->open()
            ->whereIn('task_type', TaskType::providerPortalTypes())
            ->with(['credentialingCase'])
            ->orderBy('due_date')
            ->get();
    }

    protected function loadCaseRelations(CredentialingCase $case): CredentialingCase
    {
        return $case->load([
            'payer',
            'practice',
            'location',
            'status',
            'caseType',
            'provider.user',
            'documentItems.documentType',
            'documentItems.document',
            'statusHistories.status',
        ]);
    }
}
