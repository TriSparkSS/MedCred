<?php

namespace App\Livewire\Admin\Provider;

use App\Models\Location;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\ProviderPracticeLocation;
use App\Services\AdminScopeService;
use App\Services\ProviderLocationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ProviderLocationsSection extends Component
{
    public int $providerId;

    public bool $canEdit = false;

    public bool $showModal = false;

    public string $modalMode = 'create';

    public ?int $linkId = null;

    public array $formData = [];

    public array $availableLocations = [];

    public function mount(int $providerId): void
    {
        $this->providerId = $providerId;
        $this->canEdit = (bool) Auth::guard('admin')->user()?->can('admin.providers.edit');
        $this->resetForm();
    }

    protected function rules(): array
    {
        return [
            'formData.practice_id' => 'required|exists:practices,id',
            'formData.location_id' => 'required|exists:locations,id',
            'formData.role' => 'nullable|string|max:100',
            'formData.start_date' => 'nullable|date',
            'formData.end_date' => 'nullable|date|after_or_equal:formData.start_date',
            'formData.is_primary' => 'boolean',
        ];
    }

    public function updatedFormDataPracticeId($value): void
    {
        $this->formData['location_id'] = '';
        $this->loadLocationsForPractice($value);
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

        $link = ProviderPracticeLocation::query()
            ->where('provider_id', $this->providerId)
            ->findOrFail($id);

        $this->linkId = $link->id;
        $this->modalMode = 'edit';
        $this->formData = [
            'practice_id' => (string) $link->practice_id,
            'location_id' => (string) $link->location_id,
            'role' => $link->role ?? '',
            'start_date' => $link->start_date?->format('Y-m-d') ?? '',
            'end_date' => $link->end_date?->format('Y-m-d') ?? '',
            'is_primary' => (bool) $link->is_primary,
        ];
        $this->loadLocationsForPractice($link->practice_id);
        $this->showModal = true;
    }

    public function save(ProviderLocationService $locations): void
    {
        abort_unless($this->canEdit, 403);
        $this->validate();
        $this->authorizeProvider();

        try {
            $locations->saveLink($this->provider(), $this->formData, $this->linkId);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->getMessageBag());

            return;
        }

        flash()->success($this->linkId ? 'Location updated.' : 'Location linked to provider.');
        $this->closeModal();
    }

    public function delete(int $id, ProviderLocationService $locations): void
    {
        abort_unless($this->canEdit, 403);
        $this->authorizeProvider();

        $link = ProviderPracticeLocation::query()
            ->where('provider_id', $this->providerId)
            ->findOrFail($id);

        $locations->deleteLink($link);
        flash()->info('Location unlinked from provider.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    protected function loadLocationsForPractice(mixed $practiceId): void
    {
        if (! $practiceId) {
            $this->availableLocations = [];

            return;
        }

        $this->availableLocations = Location::query()
            ->where('practice_id', $practiceId)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get()
            ->map(fn (Location $location) => [
                'id' => $location->id,
                'label' => trim($location->name.($location->is_primary ? ' (Primary)' : '').' — '.$location->city.', '.$location->state),
            ])
            ->all();
    }

    protected function resetForm(): void
    {
        $this->linkId = null;
        $this->formData = [
            'practice_id' => '',
            'location_id' => '',
            'role' => '',
            'start_date' => '',
            'end_date' => '',
            'is_primary' => false,
        ];
        $this->availableLocations = [];
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

    public function render(AdminScopeService $scope)
    {
        $admin = Auth::guard('admin')->user();
        $provider = $this->provider()->load([
            'providerPracticeLocations.practice',
            'providerPracticeLocations.location',
            'practices.locations',
        ]);

        $practiceQuery = Practice::orderBy('legal_name');
        if ($admin) {
            $scope->scopePractices($practiceQuery, $admin);
        }

        return view('livewire.admin.provider.partials.provider-locations-section', [
            'links' => $provider->providerPracticeLocations
                ->sortByDesc('is_primary')
                ->values(),
            'practices' => $practiceQuery->get(['id', 'legal_name']),
        ]);
    }
}
