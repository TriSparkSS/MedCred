<?php

namespace App\Livewire\Admin\Portal;

use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Services\AdminScopeService;
use App\Services\PortalLoginService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PortalLoginsSection extends Component
{
    #[Locked]
    public string $ownerType;

    #[Locked]
    public int $ownerId;

    #[Locked]
    public bool $canManage = false;

    public bool $showModal = false;

    public string $modalMode = 'create';

    #[Locked]
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
        return PortalLoginService::rules('formData', $this->modalMode !== 'edit');
    }

    public function openCreateModal(): void
    {
        $this->canManage = (bool) Auth::guard('admin')->user()?->can('admin.portal-credentials.manage');
        abort_unless($this->canManage, 403);
        $this->authorizeOwner();
        $this->resetForm();
        $this->modalMode = 'create';
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $this->canManage = (bool) Auth::guard('admin')->user()?->can('admin.portal-credentials.manage');
        abort_unless($this->canManage, 403);
        $this->authorizeOwner();

        $login = $this->owner()->portalLogins()->findOrFail($id);
        $this->loginId = $login->id;
        $this->modalMode = 'edit';
        $this->showPassword = true;
        $this->formData = [
            'portal_name' => $login->portal_name,
            'assigned_id' => $login->assigned_id,
            'username' => $login->username,
            'password' => (string) ($login->makeVisible('password')->password ?? ''),
            'mfa_email' => $login->mfa_email,
            'mfa_phone' => $login->mfa_phone,
            'portal_url' => $login->portal_url,
            'security_notes' => $login->security_notes,
            'account_details' => $login->account_details,
        ];
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->canManage = (bool) Auth::guard('admin')->user()?->can('admin.portal-credentials.manage');
        abort_unless($this->canManage, 403);
        $this->authorizeOwner();
        $this->validate();

        $service = app(PortalLoginService::class);

        if ($this->modalMode === 'edit' && $this->loginId) {
            $service->update($this->owner()->portalLogins()->findOrFail($this->loginId), $this->formData);
            flash()->success('Portal login updated.');
        } else {
            $service->create($this->owner(), $this->formData);
            flash()->success('Portal login added.');
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $this->canManage = (bool) Auth::guard('admin')->user()?->can('admin.portal-credentials.manage');
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
        $this->formData = PortalLoginService::emptyRow();
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
        $this->authorizeOwner();

        return view('livewire.admin.portal.partials.portal-logins-section', [
            'logins' => $this->owner()->portalLogins()->orderBy('portal_name')->get(),
            'heading' => $this->ownerType === 'practice' ? 'Practice logins' : 'Provider logins',
        ]);
    }
}
