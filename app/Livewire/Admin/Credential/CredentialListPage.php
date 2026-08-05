<?php

namespace App\Livewire\Admin\Credential;

use App\Models\CredentialingCase;
use App\Models\DelayOwner;
use App\Models\Status;
use App\Services\CredentialingCaseService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin', ['title' => 'Credentialing Tracker'])]
class CredentialListPage extends Component
{
    use WithPagination;

    #[Url(as: 'search', history: true)]
    public string $caseSearch = '';

    #[Url(as: 'category', history: true)]
    public string $filterCategory = '';

    #[Url(as: 'payer', history: true)]
    public string $filterPayerId = '';

    #[Url(as: 'practice', history: true)]
    public string $filterPracticeId = '';

    #[Url(as: 'provider', history: true)]
    public string $filterProviderId = '';

    #[Url(as: 'status', history: true)]
    public string $filterStatusId = '';

    #[Url(as: 'owner', history: true)]
    public string $filterOwnerId = '';

    #[Url(as: 'state', history: true)]
    public string $filterState = '';

    #[Url(as: 'revalidation', history: true)]
    public string $filterRevalidation = '';

    #[Url(as: 'recent', history: true)]
    public bool $filterRecentlySubmitted = false;

    public function updated($propertyName): void
    {
        if (in_array($propertyName, [
            'caseSearch', 'filterCategory', 'filterPayerId', 'filterPracticeId', 'filterProviderId',
            'filterStatusId', 'filterOwnerId', 'filterState', 'filterRevalidation', 'filterRecentlySubmitted',
        ])) {
            if ($propertyName === 'filterPracticeId') {
                $this->filterProviderId = '';
            }
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->caseSearch = '';
        $this->filterCategory = '';
        $this->filterPayerId = '';
        $this->filterPracticeId = '';
        $this->filterProviderId = '';
        $this->filterStatusId = '';
        $this->filterOwnerId = '';
        $this->filterState = '';
        $this->filterRevalidation = '';
        $this->filterRecentlySubmitted = false;
        $this->resetPage();
    }

    public function hasActiveFilters(): bool
    {
        return (bool) (trim($this->caseSearch) || $this->filterCategory || $this->filterPayerId
            || $this->filterPracticeId || $this->filterProviderId
            || $this->filterStatusId || $this->filterOwnerId || $this->filterState
            || $this->filterRevalidation || $this->filterRecentlySubmitted);
    }

    public function setFilterCategory(?string $category): void
    {
        $this->filterCategory = $this->filterCategory === $category ? '' : ($category ?? '');
        $this->resetPage();
    }

    public function updateCaseStatus(int $caseId, $statusId): void
    {
        $statusId = (int) $statusId;
        if ($statusId <= 0) {
            return;
        }

        $case = CredentialingCase::findOrFail($caseId);
        if ($case->status_id !== $statusId) {
            app(CredentialingCaseService::class)->changeStatus($case, $statusId, Auth::guard('admin')->id());
            flash()->success('Status updated.');
        }
    }

    public function inlineUpdate(int $caseId, string $field, $value): void
    {
        $allowed = ['assigned_admin_id', 'delay_owner_id'];
        if (! in_array($field, $allowed, true)) {
            return;
        }

        $case = CredentialingCase::findOrFail($caseId);
        app(CredentialingCaseService::class)->updateInline(
            $case,
            [$field => $value !== '' && $value !== null ? (int) $value : null],
            Auth::guard('admin')->id()
        );
    }

    public function toggleEscalation(int $caseId): void
    {
        $case = CredentialingCase::findOrFail($caseId);
        $case->update(['is_escalated' => ! $case->is_escalated]);
        $case->refresh();
        $case->addActivity(
            'system',
            $case->is_escalated ? 'Case escalated' : 'Escalation removed',
            Auth::guard('admin')->id()
        );
        flash()->success($case->is_escalated ? 'Case escalated.' : 'Escalation removed.');
    }

    public function render()
    {
        $query = CredentialingCase::with([
            'provider.user', 'payer', 'practice', 'status', 'delayOwner', 'assignedAdmin', 'priority',
        ])->withCount(['tasks as open_tasks_count' => fn ($q) => $q->open()]);

        if (trim($this->caseSearch) !== '') {
            $search = '%'.trim($this->caseSearch).'%';
            $query->where(function ($q) use ($search) {
                $q->where('case_number', 'like', $search)
                    ->orWhere('state', 'like', $search)
                    ->orWhereHas('provider.user', fn ($q) => $q->where('name', 'like', $search))
                    ->orWhereHas('practice', fn ($q) => $q->where('legal_name', 'like', $search)->orWhere('dba_name', 'like', $search))
                    ->orWhereHas('payer', fn ($q) => $q->where('name', 'like', $search))
                    ->orWhereHas('provider', fn ($q) => $q->where('npi', 'like', $search));
            });
        }

        if ($this->filterPayerId) {
            $query->where('payer_id', (int) $this->filterPayerId);
        }

        if ($this->filterPracticeId) {
            $query->where('practice_id', (int) $this->filterPracticeId);
        }

        if ($this->filterProviderId) {
            $query->where('provider_id', (int) $this->filterProviderId);
        }

        if ($this->filterStatusId) {
            $query->where('status_id', (int) $this->filterStatusId);
        }

        if ($this->filterOwnerId) {
            $query->where('assigned_admin_id', (int) $this->filterOwnerId);
        }

        if ($this->filterState) {
            $query->where('state', $this->filterState);
        }

        if ($this->filterRevalidation === '30') {
            $query->whereBetween('revalidation_due_date', [now(), now()->addDays(30)]);
        } elseif ($this->filterRevalidation === '60') {
            $query->whereBetween('revalidation_due_date', [now(), now()->addDays(60)]);
        } elseif ($this->filterRevalidation === '90') {
            $query->whereBetween('revalidation_due_date', [now(), now()->addDays(90)]);
        }

        if ($this->filterRecentlySubmitted) {
            $query->where('submission_date', '>=', now()->subDays(14)->toDateString());
        }

        $query->filterCategory($this->filterCategory ?: null);

        $cases = $query->orderByDesc('last_action_at')->paginate(15);

        $closedCategories = ['approved', 'closed'];
        $stats = [
            'total_active' => CredentialingCase::whereHas('status', fn ($q) => $q->whereNotIn('dashboard_category', $closedCategories))->count(),
            'pending_provider' => CredentialingCase::filterCategory('provider')->count(),
            'pending_payer' => CredentialingCase::filterCategory('payer')->count(),
            'overdue' => CredentialingCase::filterCategory('overdue')->count(),
        ];

        $statuses = Status::where('is_active', true)->orderBy('sort_order')->get();
        $payers = \App\Models\Payer::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $practices = \App\Models\Practice::orderBy('legal_name')->get(['id', 'legal_name', 'client_code']);
        $providers = $this->filterPracticeId
            ? \App\Models\ProviderDetails::query()
                ->whereHas('practices', fn ($q) => $q->where('practices.id', (int) $this->filterPracticeId))
                ->with('user:id,name')
                ->get()
            : collect();

        $delayOwners = DelayOwner::where('is_active', true)->orderBy('name')->get();
        $admins = \App\Models\Admin::assignable()->get(['id', 'name', 'username']);

        return view('livewire.admin.credential.credential-list-page', compact(
            'cases', 'stats', 'statuses', 'payers', 'practices', 'providers', 'delayOwners', 'admins'
        ));
    }
}
