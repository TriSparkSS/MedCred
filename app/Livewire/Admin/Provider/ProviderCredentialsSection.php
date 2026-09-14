<?php

namespace App\Livewire\Admin\Provider;

use App\Enums\ProviderCredentialStatus;
use App\Enums\ProviderCredentialType;
use App\Models\ProviderCredential;
use App\Models\ProviderDetails;
use App\Services\AdminScopeService;
use App\Services\ProviderCredentialService;
use App\Support\UsStates;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ProviderCredentialsSection extends Component
{
    public int $providerId;

    public bool $canEdit = false;

    public bool $showModal = false;

    public string $modalMode = 'create';

    public ?int $credentialId = null;

    public array $formData = [];

    public function mount(int $providerId): void
    {
        $this->providerId = $providerId;
        $this->canEdit = (bool) Auth::guard('admin')->user()?->can('admin.providers.edit');
        $this->resetForm();
    }

    protected function rules(): array
    {
        return [
            'formData.credential_type' => ['required', Rule::enum(ProviderCredentialType::class)],
            'formData.state' => ['required', 'string', 'size:2', Rule::in(UsStates::codes())],
            'formData.number' => 'required|string|max:50',
            'formData.issue_date' => 'nullable|date',
            'formData.expiry_date' => 'nullable|date|after_or_equal:formData.issue_date',
            'formData.status' => ['required', Rule::enum(ProviderCredentialStatus::class)],
            'formData.is_primary' => 'boolean',
            'formData.notes' => 'nullable|string|max:1000',
        ];
    }

    public function openCreateModal(): void
    {
        abort_unless($this->canEdit, 403);
        $this->resetForm();
        $this->modalMode = 'create';
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        abort_unless($this->canEdit, 403);

        $credential = ProviderCredential::query()
            ->where('provider_id', $this->providerId)
            ->findOrFail($id);

        $this->credentialId = $credential->id;
        $this->modalMode = 'edit';
        $this->formData = [
            'credential_type' => $credential->credential_type->value,
            'state' => $credential->state,
            'number' => $credential->number,
            'issue_date' => $credential->issue_date?->format('Y-m-d') ?? '',
            'expiry_date' => $credential->expiry_date?->format('Y-m-d') ?? '',
            'status' => $credential->status->value,
            'is_primary' => (bool) $credential->is_primary,
            'notes' => $credential->notes ?? '',
        ];
        $this->showModal = true;
    }

    public function save(ProviderCredentialService $credentials): void
    {
        abort_unless($this->canEdit, 403);
        $this->validate();
        $this->authorizeProvider();

        try {
            $credentials->save($this->provider(), $this->formData, $this->credentialId);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->getMessageBag());

            return;
        }

        flash()->success($this->credentialId ? 'Credential updated.' : 'Credential added.');
        $this->closeModal();
    }

    public function delete(int $id, ProviderCredentialService $credentials): void
    {
        abort_unless($this->canEdit, 403);
        $this->authorizeProvider();

        $credential = ProviderCredential::query()
            ->where('provider_id', $this->providerId)
            ->findOrFail($id);

        $credentials->delete($credential);
        flash()->info('Credential removed.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->credentialId = null;
        $this->formData = [
            'credential_type' => ProviderCredentialType::License->value,
            'state' => '',
            'number' => '',
            'issue_date' => '',
            'expiry_date' => '',
            'status' => ProviderCredentialStatus::Active->value,
            'is_primary' => false,
            'notes' => '',
        ];
        $this->resetValidation();
    }

    protected function provider(): ProviderDetails
    {
        return ProviderDetails::findOrFail($this->providerId);
    }

    protected function authorizeProvider(): void
    {
        $admin = Auth::guard('admin')->user();
        if ($admin && ! app(AdminScopeService::class)->canAccessProvider($admin, $this->provider())) {
            abort(403);
        }
    }

    public function render()
    {
        $credentials = ProviderCredential::query()
            ->where('provider_id', $this->providerId)
            ->with('document')
            ->orderBy('credential_type')
            ->orderBy('state')
            ->get();

        return view('livewire.admin.provider.partials.provider-credentials-section', [
            'credentials' => $credentials,
            'states' => UsStates::all(),
            'types' => ProviderCredentialType::cases(),
            'statuses' => ProviderCredentialStatus::cases(),
        ]);
    }
}
