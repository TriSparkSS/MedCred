<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\CredentialingCase;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\Task;
use Illuminate\Support\Collection;

class PortalAssignedItemsService
{
    protected array $closedCategories = ['approved', 'closed'];

    public function forProvider(ProviderDetails $provider, string $status = 'open'): Collection
    {
        $isOpen = $status !== 'closed';
        $items = collect();

        $items = $items->concat($this->caseItems(
            CredentialingCase::where('provider_id', $provider->id)->with(['payer', 'status']),
            $isOpen,
            'provider.cases.show',
        ));

        if ($isOpen) {
            $items = $items->concat(
                app(ProviderDashboardService::class)->outstandingDocuments($provider)->map(fn (array $item) => [
                    'type' => 'document',
                    'type_label' => 'Document',
                    'id' => 'doc-'.$item['case_id'].'-'.$item['document_type_id'],
                    'title' => $item['document_type'],
                    'subtitle' => $item['case_number'].($item['payer'] ? ' · '.$item['payer'] : ''),
                    'status' => 'Requested',
                    'status_class' => 'warning',
                    'due_date' => null,
                    'url' => route('provider.documents'),
                ])
            );
        }

        $items = $items->concat($this->taskItems(
            Task::query()->where('provider_id', $provider->id),
            $isOpen,
            route('provider.action-items'),
        ));

        return $this->sortItems($items);
    }

    public function forPractice(Practice $practice, string $status = 'open'): Collection
    {
        $isOpen = $status !== 'closed';
        $items = collect();

        $items = $items->concat($this->caseItems(
            CredentialingCase::where('practice_id', $practice->id)->with(['payer', 'status', 'provider.user']),
            $isOpen,
            'practice.cases.show',
            includeProvider: true,
        ));

        if ($isOpen) {
            $items = $items->concat(
                app(PracticeDashboardService::class)->outstandingDocuments($practice)->map(fn (array $item) => [
                    'type' => 'document',
                    'type_label' => 'Document',
                    'id' => 'doc-'.$item['case_id'].'-'.$item['document_type_id'],
                    'title' => $item['document_type'],
                    'subtitle' => trim(($item['provider'] ?? '').' · '.$item['case_number'].($item['payer'] ? ' · '.$item['payer'] : ''), ' ·'),
                    'status' => 'Requested',
                    'status_class' => 'warning',
                    'due_date' => null,
                    'url' => route('practice.documents'),
                ])
            );
        }

        $providerIds = $practice->providers()->pluck('provider_details.id');
        $taskQuery = $providerIds->isEmpty()
            ? Task::query()->whereRaw('0 = 1')
            : Task::query()->whereIn('provider_id', $providerIds)->with('provider.user');

        $items = $items->concat($this->taskItems(
            $taskQuery,
            $isOpen,
            route('practice.action-items'),
            includeProvider: true,
        ));

        return $this->sortItems($items);
    }

    public function countsForProvider(ProviderDetails $provider): array
    {
        return [
            'open' => $this->forProvider($provider, 'open')->count(),
            'closed' => $this->forProvider($provider, 'closed')->count(),
        ];
    }

    public function countsForPractice(Practice $practice): array
    {
        return [
            'open' => $this->forPractice($practice, 'open')->count(),
            'closed' => $this->forPractice($practice, 'closed')->count(),
        ];
    }

    protected function caseItems($query, bool $isOpen, string $showRoute, bool $includeProvider = false): Collection
    {
        return $query
            ->whereHas('status', function ($statusQuery) use ($isOpen) {
                $isOpen
                    ? $statusQuery->whereNotIn('dashboard_category', $this->closedCategories)
                    : $statusQuery->whereIn('dashboard_category', $this->closedCategories);
            })
            ->latest()
            ->limit(50)
            ->get()
            ->map(function (CredentialingCase $case) use ($showRoute, $includeProvider, $isOpen) {
                $subtitle = $case->payer->name ?? '';
                if ($includeProvider) {
                    $subtitle = trim(($case->provider->user->name ?? '').($subtitle ? ' · '.$subtitle : ''));
                }

                return [
                    'type' => 'case',
                    'type_label' => 'Application',
                    'id' => 'case-'.$case->id,
                    'title' => $case->case_number,
                    'subtitle' => $subtitle,
                    'status' => $case->status->name ?? ($isOpen ? 'Open' : 'Closed'),
                    'status_class' => $isOpen ? 'primary' : 'secondary',
                    'due_date' => $case->next_follow_up_date,
                    'url' => route($showRoute, $case),
                ];
            });
    }

    protected function taskItems($query, bool $isOpen, string $url, bool $includeProvider = false): Collection
    {
        return $query
            ->whereIn('task_type', TaskType::providerPortalTypes())
            ->when(
                $isOpen,
                fn ($q) => $q->open(),
                fn ($q) => $q->where(function ($inner) {
                    $inner->whereNotNull('completed_at')
                        ->orWhereIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value]);
                })
            )
            ->with(['credentialingCase.payer', 'priority'])
            ->orderBy('due_date')
            ->limit(50)
            ->get()
            ->map(function (Task $task) use ($url, $includeProvider, $isOpen) {
                $subtitle = $task->credentialingCase?->case_number ?? '';
                if ($includeProvider) {
                    $subtitle = trim(($task->provider->user->name ?? '').($subtitle ? ' · '.$subtitle : ''));
                }

                return [
                    'type' => 'task',
                    'type_label' => 'Task',
                    'id' => 'task-'.$task->id,
                    'title' => $task->title,
                    'subtitle' => $subtitle,
                    'status' => $task->status?->label() ?? ($isOpen ? 'Open' : 'Closed'),
                    'status_class' => $task->kanbanColumn() === 'overdue' ? 'danger' : ($isOpen ? 'info' : 'secondary'),
                    'due_date' => $task->due_date,
                    'url' => $url,
                ];
            });
    }

    protected function sortItems(Collection $items): Collection
    {
        return $items
            ->sortBy(fn (array $item) => $item['due_date']?->timestamp ?? PHP_INT_MAX)
            ->values();
    }
}
