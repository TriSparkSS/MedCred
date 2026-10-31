<?php

namespace App\Livewire\Admin\Provider;

use App\Enums\ProviderStatus;
use App\Models\Location;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\Specialty;
use App\Services\AdminScopeService;
use App\Support\UsStates;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin', ['title' => 'Providers'])]
class ProviderListPage extends Component
{
    use WithPagination;

    public $search = '';

    public $formData = [];

    #[Locked]
    public $providerId = null;

    public $specialties = [];

    public $filterSpecialty = '';

    public $filterStatus = '';

    public $filterPractice = '';

    public $filterLocation = '';

    public $filterState = '';

    public $filterPayer = '';

    public $filterCompliance = '';

    protected $rules = [
        'formData.user_id' => 'required|exists:users,id',
        'formData.specialty_id' => 'nullable|exists:specialties,id',
        'formData.npi' => 'required|string|max:50|unique:provider_details,npi',
        'formData.practice' => 'required|string|max:255',
        'formData.address' => 'required|string|max:500',
        'formData.city' => 'required|string|max:100',
        'formData.state' => 'required|string|max:50',
        'formData.zip' => 'required|string|max:20',
        'formData.status' => 'required|string|max:50',
    ];

    public function mount()
    {
        $this->specialties = Specialty::orderBy('name')->get();
    }

    public function updated($propertyName)
    {
        if (in_array($propertyName, [
            'search',
            'filterSpecialty',
            'filterStatus',
            'filterPractice',
            'filterLocation',
            'filterState',
            'filterPayer',
            'filterCompliance',
        ], true)) {
            $this->resetPage();
        }
    }

    public function updatedFilterPractice(): void
    {
        $this->filterLocation = '';
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filterSpecialty = '';
        $this->filterStatus = '';
        $this->filterPractice = '';
        $this->filterLocation = '';
        $this->filterState = '';
        $this->filterPayer = '';
        $this->filterCompliance = '';
        $this->resetPage();
    }

    public function openCreateModal()
    {
        // Creation moved to a separate page. Use the create page instead of modal.
        return redirect()->route('admin.providers.create');
    }

    public function openEditModal($providerId)
    {
        return redirect()->route('admin.providers.edit', $providerId);
    }

    // Editing moved to separate page; listing only handles navigation and deletion.

    public function delete(int $id): void
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin && ($admin->can('admin.providers.delete') || $admin->can('admin.providers.manage')), 403);

        $provider = ProviderDetails::findOrFail($id);
        abort_unless(app(AdminScopeService::class)->canAccessProvider($admin, $provider), 403);

        $this->providerId = $id;
        sweetalert()
            ->showDenyButton()
            ->info('Are you sure you want to delete this provider?');
    }

    #[On('sweetalert:confirmed')]
    public function onConfirmed(array $payload = []): void
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin && ($admin->can('admin.providers.delete') || $admin->can('admin.providers.manage')), 403);

        $provider = ProviderDetails::with('user')->findOrFail($this->providerId);
        abort_unless(app(AdminScopeService::class)->canAccessProvider($admin, $provider), 403);

        $provider->user?->update(['status' => 0]);
        $provider->delete();
        $this->providerId = null;
        flash()->info('Provider successfully deleted.');
    }

    #[On('sweetalert:denied')]
    public function onDeny(array $payload): void
    {
        $this->providerId = null;
        flash()->info('Deletion cancelled.');
    }

    public function closeModal()
    {
        // No modal on listing; kept for compatibility but does nothing.
    }

    private function resetForm()
    {
        $this->formData = [];
        $this->providerId = null;
        $this->resetValidation();
    }

    public function render(AdminScopeService $scope)
    {
        $admin = Auth::guard('admin')->user();
        $query = ProviderDetails::with([
            'user',
            'specialty',
            'practices',
            'providerPracticeLocations.location',
            'providerPracticeLocations.practice',
            'credentialingCases.payer',
            'credentialingCases.status',
            'credentialingCases.documentItems',
            'documents',
            'credentials',
        ]);

        if ($admin) {
            $scope->scopeProviders($query, $admin);
        }

        if ($this->filterSpecialty) {
            $query->where('specialty_id', $this->filterSpecialty);
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterLocation) {
            $query->whereHas('providerPracticeLocations', function ($q) {
                $q->where('location_id', $this->filterLocation);
                if ($this->filterPractice) {
                    $q->where('practice_id', $this->filterPractice);
                }
            });
        } elseif ($this->filterPractice) {
            $practiceId = $this->filterPractice;
            $query->where(function ($q) use ($practiceId) {
                $q->whereHas('practices', fn ($pq) => $pq->where('practices.id', $practiceId))
                    ->orWhereHas('providerPracticeLocations', fn ($ppl) => $ppl->where('practice_id', $practiceId));
            });
        }

        if ($this->filterState) {
            $state = $this->filterState;
            $query->where(function ($q) use ($state) {
                $q->where('state', $state)
                    ->orWhereHas('credentialingCases', fn ($cq) => $cq->where('state', $state))
                    ->orWhereHas('providerPracticeLocations.location', fn ($lq) => $lq->where('state', $state));
            });
        }

        if ($this->filterPayer) {
            $query->whereHas('credentialingCases', fn ($q) => $q->where('payer_id', (int) $this->filterPayer));
        }

        if ($this->filterCompliance === 'missing_docs') {
            $query->where(function ($q) {
                $q->doesntHave('documents')
                    ->orWhereHas('credentialingCases.documentItems', function ($di) {
                        $di->where('is_required', true)
                            ->where(function ($sub) {
                                $sub->where('is_received', false)
                                    ->orWhereHas('document', fn ($dq) => $dq->where('verification_status', 'rejected'));
                            });
                    });
            });
        } elseif ($this->filterCompliance === 'expiring_soon') {
            $today = now()->toDateString();
            $in30Days = now()->addDays(30)->toDateString();
            $query->where(function ($q) use ($today, $in30Days) {
                $q->whereHas('documents', fn ($dq) => $dq->whereNotNull('expiry_date')
                    ->whereDate('expiry_date', '>=', $today)
                    ->whereDate('expiry_date', '<=', $in30Days))
                    ->orWhereHas('credentials', fn ($cq) => $cq->whereNotNull('expiry_date')
                        ->whereDate('expiry_date', '>=', $today)
                        ->whereDate('expiry_date', '<=', $in30Days));
            });
        } elseif ($this->filterCompliance === 'expired') {
            $today = now()->toDateString();
            $query->where(function ($q) use ($today) {
                $q->whereHas('documents', fn ($dq) => $dq->whereNotNull('expiry_date')
                    ->whereDate('expiry_date', '<', $today))
                    ->orWhereHas('credentials', fn ($cq) => $cq->whereNotNull('expiry_date')
                        ->whereDate('expiry_date', '<', $today));
            });
        }

        if ($this->search) {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term) {
                $q->whereHas('user', fn ($uq) => $uq->where('name', 'like', $term))
                    ->orWhereHas('specialty', fn ($sq) => $sq->where('name', 'like', $term))
                    ->orWhereHas('practices', function ($pq) use ($term) {
                        $pq->where('legal_name', 'like', $term)
                            ->orWhere('dba_name', 'like', $term);
                    })
                    ->orWhere('npi', 'like', $term)
                    ->orWhere('caqh_id', 'like', $term)
                    ->orWhere('practice', 'like', $term);
            });
        }

        $query->orderBy('created_at', 'desc');
        $providers = $query->paginate(10);

        $statsBase = ProviderDetails::query();
        if ($admin) {
            $scope->scopeProviders($statsBase, $admin);
        }

        $stats = [
            'total' => (clone $statsBase)->count(),
            'active' => (clone $statsBase)->where('status', ProviderStatus::ACTIVE->value)->count(),
            'inactive' => (clone $statsBase)->where('status', ProviderStatus::INACTIVE->value)->count(),
            'on_hold' => (clone $statsBase)->where('status', ProviderStatus::ON_HOLD->value)->count(),
        ];

        $specialties = Specialty::orderBy('name')->get();

        $practiceQuery = Practice::orderBy('legal_name');
        if ($admin) {
            $scope->scopePractices($practiceQuery, $admin);
        }

        $facilityLocations = collect();
        if ($this->filterPractice) {
            $facilityLocations = Location::query()
                ->where('practice_id', $this->filterPractice)
                ->orderByDesc('is_primary')
                ->orderBy('name')
                ->get(['id', 'name', 'city', 'state', 'is_primary']);
        }

        return view('livewire.admin.provider.provider-list-page', compact(
            'providers',
            'stats',
            'specialties',
            'facilityLocations'
        ) + [
            'practices' => $practiceQuery->get(['id', 'legal_name']),
            'payers' => Payer::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'usStates' => UsStates::all(),
        ]);
    }
}
