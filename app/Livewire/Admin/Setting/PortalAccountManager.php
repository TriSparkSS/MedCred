<?php

namespace App\Livewire\Admin\Setting;

use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\User;
use App\Services\AdminScopeService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin', ['title' => 'Portal Accounts | Settings'])]
class PortalAccountManager extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterType = '';

    public bool $showModal = false;

    public ?int $editingUserId = null;

    public string $accountType = '';

    public string $linkedLabel = '';

    public string $userName = '';

    public string $userEmail = '';

    public string $userPhone = '';

    public string $userPassword = '';

    public function updated(string $propertyName): void
    {
        if (in_array($propertyName, ['search', 'filterType'], true)) {
            $this->resetPage();
        }
    }

    public function openEditModal(int $id): void
    {
        $user = $this->findScopedUser($id);

        $this->editingUserId = $user->id;
        $this->userName = $user->name;
        $this->userEmail = $user->email;
        $this->userPhone = (string) ($user->phone ?? '');
        $this->userPassword = '';
        $this->accountType = $user->practice ? 'practice' : 'provider';
        $this->linkedLabel = $user->practice
            ? $user->practice->legal_name.($user->practice->client_code ? ' ('.$user->practice->client_code.')' : '')
            : ($user->providerDetails?->user?->name ?? $user->name);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $user = $this->findScopedUser((int) $this->editingUserId);
        $practice = $user->practice;

        $this->validate([
            'userName' => 'required|string|max:255',
            'userEmail' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
                ...($practice ? [Rule::unique('practices', 'email')->ignore($practice->id)] : []),
            ],
            'userPhone' => 'nullable|string|max:30',
            'userPassword' => 'nullable|string|min:6',
        ]);

        $payload = [
            'name' => $this->userName,
            'email' => $this->userEmail,
            'phone' => $this->userPhone ?: null,
        ];

        if (filled($this->userPassword)) {
            $payload['password'] = $this->userPassword;
        }

        $user->update($payload);

        if ($practice) {
            $practice->update([
                'email' => $this->userEmail,
                'phone' => $this->userPhone ?: $practice->phone,
            ]);
        }

        flash()->success('Portal account updated.');
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->editingUserId = null;
        $this->accountType = '';
        $this->linkedLabel = '';
        $this->userName = '';
        $this->userEmail = '';
        $this->userPhone = '';
        $this->userPassword = '';
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.admin.setting.portal-account-manager', [
            'records' => $this->accountQuery()
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    protected function accountQuery()
    {
        $admin = Auth::guard('admin')->user();
        $scope = app(AdminScopeService::class);

        $query = User::query()
            ->with(['practice:id,user_id,legal_name,client_code,email', 'providerDetails:id,user_id,npi'])
            ->where(function ($q) {
                $q->whereHas('practice')->orWhereHas('providerDetails');
            });

        if ($admin) {
            $query->where(function ($q) use ($scope, $admin) {
                $q->whereHas('practice', fn ($pq) => $scope->scopePractices($pq, $admin))
                    ->orWhereHas('providerDetails', fn ($pq) => $scope->scopeProviders($pq, $admin));
            });
        }

        if ($this->filterType === 'practice') {
            $query->whereHas('practice');
        } elseif ($this->filterType === 'provider') {
            $query->whereHas('providerDetails');
        }

        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhereHas('practice', function ($pq) use ($term) {
                        $pq->where('legal_name', 'like', $term)
                            ->orWhere('dba_name', 'like', $term)
                            ->orWhere('client_code', 'like', $term);
                    })
                    ->orWhereHas('providerDetails', fn ($pq) => $pq->where('npi', 'like', $term));
            });
        }

        return $query;
    }

    protected function findScopedUser(int $id): User
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.portal-credentials.manage'), 403);

        $user = User::with(['practice', 'providerDetails'])->findOrFail($id);
        $admin = Auth::guard('admin')->user();

        if ($user->practice instanceof Practice && ! app(AdminScopeService::class)->canAccessPractice($admin, $user->practice->id)) {
            abort(403);
        }

        if ($user->providerDetails instanceof ProviderDetails && ! app(AdminScopeService::class)->canAccessProvider($admin, $user->providerDetails)) {
            abort(403);
        }

        if (! $user->practice && ! $user->providerDetails) {
            abort(404);
        }

        return $user;
    }
}
