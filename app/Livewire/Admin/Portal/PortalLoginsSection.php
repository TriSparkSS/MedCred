<?php

namespace App\Livewire\Admin\Portal;

use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Services\AdminScopeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PortalLoginsSection extends Component
{
    public string $ownerType;

    public int $ownerId;

    public bool $canManage = false;

    public bool $showModal = false;

    public string $modalMode = 'create';

    public ?int $loginId = null;

    public array $formData = [];

    public bool $showPassword = true;

    public function mount(string $ownerType, int $ownerId): void
    {
        abort_unless(in_array($ownerType, ['practice', 'provider'], true), 404);

        $this->ownerType = $ownerType;
        $this->ownerId = $ownerId;
        $this->canManage = (bool) Auth::guard('admin')->user()?->can('admin.portal-credentials.manage');
        $this->authorizeOwner();
        $this->resetForm();
    }

    protected function rules(): array
    {
        return [
            'formData.portal_name' => 'required|string|max:255',
            'formData.username' => 'nullable|string|max:255',
            'formData.password' => ($this->modalMode === 'edit' ? 'nullable' : 'required').'|string|max:500',
            'formData.mfa_email' => 'nullable|email|max:255',
            'formData.mfa_phone' => 'nullable|string|max:30',
            'formData.security_notes' => 'nullable|string|max:5000',
            'formData.account_details' => 'nullable|string|max:5000',
        ];
    }

    public function openCreateModal(): void
    {
        abort_unless($this->canManage, 403);
        $this->resetForm();
        $this->modalMode = 'create';
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        abort_unless($this->canManage, 403);

        $login = $this->owner()->portalLogins()->findOrFail($id);
        $this->loginId = $login->id;
        $this->modalMode = 'edit';
        $this->showPassword = true;
        $this->formData = [
            'portal_name' => $login->portal_name,
            'username' => $login->username,
            'password' => (string) ($login->makeVisible('password')->password ?? ''),
            'mfa_email' => $login->mfa_email,
            'mfa_phone' => $login->mfa_phone,
            'security_notes' => $login->security_notes,
            'account_details' => $login->account_details,
        ];
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        abort_unless($this->canManage, 403);
        $this->authorizeOwner();
        $this->validate();

        $payload = [
            'portal_name' => $this->formData['portal_name'],
            'username' => $this->formData['username'] ?: null,
            'mfa_email' => $this->formData['mfa_email'] ?: null,
            'mfa_phone' => $this->formData['mfa_phone'] ?: null,
            'security_notes' => $this->formData['security_notes'] ?: null,
            'account_details' => $this->formData['account_details'] ?: null,
        ];

        if (filled($this->formData['password'] ?? null)) {
            $payload['password'] = $this->formData['password'];
        }

        if ($this->modalMode === 'edit' && $this->loginId) {
            $this->owner()->portalLogins()->findOrFail($this->loginId)->update($payload);
            flash()->success('Portal login updated.');
        } else {
            $this->owner()->portalLogins()->create($payload);
            flash()->success('Portal login added.');
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        abort_unless($this->canManage, 403);
        $this->authorizeOwner();
        $this->owner()->portalLogins()->findOrFail($id)->delete();
        flash()->info('Portal login removed.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function togglePasswordVisibility(): void
    {
        $this->showPassword = ! $this->showPassword;
    }

    protected function resetForm(): void
    {
        $this->loginId = null;
        $this->showPassword = true;
        $this->formData = [
            'portal_name' => '',
            'username' => '',
            'password' => '',
            'mfa_email' => '',
            'mfa_phone' => '',
            'security_notes' => '',
            'account_details' => '',
        ];
        $this->resetValidation();
    }

    protected function owner(): Model
    {
        return match ($this->ownerType) {
            'practice' => Practice::findOrFail($this->ownerId),
            default => ProviderDetails::findOrFail($this->ownerId),
        };
    }

    protected function authorizeOwner(): void
    {
        $admin = Auth::guard('admin')->user();
        if (! $admin) {
            return;
        }

        $scope = app(AdminScopeService::class);
        $owner = $this->owner();

        if ($owner instanceof Practice && ! $scope->canAccessPractice($admin, $owner->id)) {
            abort(403);
        }

        if ($owner instanceof ProviderDetails && ! $scope->canAccessProvider($admin, $owner)) {
            abort(403);
        }
    }

    public function render()
    {
        return view('livewire.admin.portal.partials.portal-logins-section', [
            'logins' => $this->owner()->portalLogins()->orderBy('portal_name')->get(),
            'heading' => $this->ownerType === 'practice' ? 'Practice logins' : 'Provider logins',
        ]);
    }
}
