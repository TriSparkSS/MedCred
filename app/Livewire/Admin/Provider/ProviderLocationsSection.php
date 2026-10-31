<?php

namespace App\Livewire\Admin\Provider;

use App\Models\Address;
use App\Models\Location;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\ProviderPracticeLocation;
use App\Services\AdminScopeService;
use App\Services\ProviderLocationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ProviderLocationsSection extends Component
{
    #[Locked]
    public int $providerId;

    #[Locked]
    public bool $canEdit = false;

    public bool $showModal = false;

    public string $modalMode = 'create';

    #[Locked]
    public ?int $linkId = null;

    public array $formData = [];

    public array $availableLocations = [];

    public function mount(int $providerId): void
    {
        $this->providerId = $providerId;
        $this->canEdit = (bool) Auth::guard('admin')->user()?->can('admin.providers.edit');
        $this->authorizeProvider();
        $this->resetForm();
    }

    protected function rules(): array
    {
        return [
            'formData.practice_id' => 'required|exists:practices,id',
            'formData.location_id' => [
                'required',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $raw = (string) $value;
                    if (str_starts_with($raw, 'address:')) {
                        $addrId = (int) substr($raw, strlen('address:'));
                        $exists = Address::query()
                            ->whereKey($addrId)
                            ->where('addressable_type', Practice::class)
                            ->where('addressable_id', (int) ($this->formData['practice_id'] ?? 0))
                            ->exists();
                        if (! $exists) {
                            $fail('The selected location is invalid.');
                        }

                        return;
                    }

                    if (! is_numeric($raw) || ! Location::whereKey((int) $raw)->exists()) {
                        $fail('The selected location is invalid.');
                    }
                },
            ],
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
        $this->canEdit = (bool) Auth::guard('admin')->user()?->can('admin.providers.edit');
        abort_unless($this->canEdit, 403);
        $this->authorizeProvider();
        $this->resetForm();
        $admin = Auth::guard('admin')->user();
        $scope = app(AdminScopeService::class);
        $practices = $this->provider()->practices;
        if ($admin) {
            $practices = $practices->filter(fn (Practice $p) => $scope->canAccessPractice($admin, (int) $p->id));
        }
        $practice = $practices->sortByDesc(fn (Practice $p) => (int) ($p->pivot->primary_flag ?? 0))->first();
        if ($practice) {
            $this->formData['practice_id'] = (string) $practice->id;
            $this->loadLocationsForPractice($practice->id);
        }
        $this->modalMode = 'create';
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $this->canEdit = (bool) Auth::guard('admin')->user()?->can('admin.providers.edit');
        abort_unless($this->canEdit, 403);
        $this->authorizeProvider();

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
        $this->canEdit = (bool) Auth::guard('admin')->user()?->can('admin.providers.edit');
        abort_unless($this->canEdit, 403);
        $this->authorizeProvider();
        $this->validate();

        $admin = Auth::guard('admin')->user();
        if ($admin && ! app(AdminScopeService::class)->canAccessPractice($admin, (int) $this->formData['practice_id'])) {
            abort(403);
        }

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
        $this->canEdit = (bool) Auth::guard('admin')->user()?->can('admin.providers.edit');
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

        $practice = Practice::with(['addresses', 'locations'])->find($practiceId);
        if (! $practice) {
            $this->availableLocations = [];

            return;
        }

        $options = [];
        $typeOrder = ['primary', 'alternative', 'mailing', 'billing'];
        $typeLabels = [
            'primary' => 'Primary Location Address',
            'alternative' => 'Alternative Address (Location)',
            'mailing' => 'Mailing Address',
            'billing' => 'Billing Address',
        ];

        $sortedAddresses = $practice->addresses
            ->sortBy(fn ($addr) => array_search($addr->type, $typeOrder, true) === false ? 99 : array_search($addr->type, $typeOrder, true));

        foreach ($sortedAddresses as $address) {
            $street = trim(($address->address1 ?? '').($address->address2 ? ', '.$address->address2 : ''));
            if ($street === '' && ! filled($address->city) && ! filled($address->zip_code)) {
                continue;
            }

            $typeLabel = $typeLabels[$address->type] ?? ucfirst((string) $address->type).' Address';
            $namePart = filled($address->location_name) ? ' ('.$address->location_name.')' : '';
            $cityState = trim(($address->city ?? '').', '.($address->state ?? ''), ' ,');

            $options[] = [
                'id' => 'address:'.$address->id,
                'label' => trim($typeLabel.$namePart.($street !== '' ? ' — '.$street : '').($cityState !== '' ? ', '.$cityState : '')),
            ];
        }

        $facilityLocations = $practice->locations
            ->sortByDesc('is_primary')
            ->sortBy('name')
            ->values();

        foreach ($facilityLocations as $location) {
            $street = trim(($location->address1 ?? '').($location->address2 ? ', '.$location->address2 : ''));
            $cityState = trim(($location->city ?? '').', '.($location->state ?? ''), ' ,');
            $options[] = [
                'id' => $location->id,
                'label' => trim($location->name.($location->is_primary ? ' (Primary)' : '').($street !== '' ? ' — '.$street : '').($cityState !== '' ? ', '.$cityState : '')),
            ];
        }

        $this->availableLocations = $options;
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
        $this->authorizeProvider();

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
