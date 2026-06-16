<?php

namespace App\Livewire\Admin\ProviderPractice;

use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\ProviderPractice;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin', ['title' => 'Provider Practice Assignment'])]
class ProviderPracticeAssignmentPage extends Component
{
    use WithPagination;

    public $search = '';
    public $assignmentId = null;
    public $formData = [
        'provider_id' => '',
        'practice_id' => '',
        'primary_flag' => false,
        'start_date' => '',
        'end_date' => '',
    ];

    protected $rules = [
        'formData.provider_id' => 'required|exists:provider_details,id',
        'formData.practice_id' => 'required|exists:practices,id',
        'formData.primary_flag' => 'boolean',
        'formData.start_date' => 'nullable|date',
        'formData.end_date' => 'nullable|date|after_or_equal:formData.start_date',
    ];

    public function updated($propertyName)
    {
        if ($propertyName === 'search') {
            $this->resetPage();
        }
    }

    public function save()
    {
        $this->validate();

        $existingAssignment = ProviderPractice::query()
            ->where('provider_id', $this->formData['provider_id'])
            ->where('practice_id', $this->formData['practice_id'])
            ->when($this->assignmentId, fn ($query) => $query->whereKeyNot($this->assignmentId))
            ->exists();

        if ($existingAssignment) {
            $this->addError('formData.practice_id', 'This provider is already assigned to this practice.');

            return null;
        }

        if ($this->formData['primary_flag']) {
            ProviderPractice::query()
                ->where('provider_id', $this->formData['provider_id'])
                ->when($this->assignmentId, fn ($query) => $query->whereKeyNot($this->assignmentId))
                ->update(['primary_flag' => false]);
        }

        ProviderPractice::query()->updateOrCreate(
            ['id' => $this->assignmentId],
            [
                'provider_id' => $this->formData['provider_id'],
                'practice_id' => $this->formData['practice_id'],
                'primary_flag' => (bool) $this->formData['primary_flag'],
                'start_date' => $this->formData['start_date'] ?: null,
                'end_date' => $this->formData['end_date'] ?: null,
            ]
        );

        flash()->success($this->assignmentId ? 'Assignment updated successfully!' : 'Provider assigned to practice successfully!');
        $this->resetForm();
    }

    public function edit(int $assignmentId): void
    {
        $assignment = ProviderPractice::findOrFail($assignmentId);

        $this->assignmentId = $assignment->id;
        $this->formData = [
            'provider_id' => (string) $assignment->provider_id,
            'practice_id' => (string) $assignment->practice_id,
            'primary_flag' => (bool) $assignment->primary_flag,
            'start_date' => $assignment->start_date?->format('Y-m-d') ?? '',
            'end_date' => $assignment->end_date?->format('Y-m-d') ?? '',
        ];
    }

    public function delete(int $assignmentId): void
    {
        $this->assignmentId = $assignmentId;
        sweetalert()
            ->showDenyButton()
            ->info('Are you sure you want to remove this provider practice assignment?');
    }

    #[On('sweetalert:confirmed')]
    public function onConfirmed(array $payload): void
    {
        ProviderPractice::findOrFail($this->assignmentId)->delete();
        $this->resetForm();
        flash()->info('Assignment removed successfully.');
    }

    #[On('sweetalert:denied')]
    public function onDeny(array $payload): void
    {
        $this->assignmentId = null;
        flash()->info('Deletion cancelled.');
    }

    public function resetForm(): void
    {
        $this->assignmentId = null;
        $this->formData = [
            'provider_id' => '',
            'practice_id' => '',
            'primary_flag' => false,
            'start_date' => '',
            'end_date' => '',
        ];
        $this->resetValidation();
    }

    public function render()
    {
        $query = ProviderPractice::with('provider.user', 'provider.specialty', 'practice');

        if ($this->search) {
            $search = '%' . $this->search . '%';
            $query->where(function ($q) use ($search) {
                $q->whereHas('provider.user', fn ($userQuery) => $userQuery->where('name', 'like', $search))
                    ->orWhereHas('provider', fn ($providerQuery) => $providerQuery->where('npi', 'like', $search))
                    ->orWhereHas('practice', function ($practiceQuery) use ($search) {
                        $practiceQuery->where('legal_name', 'like', $search)
                            ->orWhere('dba_name', 'like', $search)
                            ->orWhere('group_npi', 'like', $search);
                    });
            });
        }

        return view('livewire.admin.provider-practice.provider-practice-assignment-page', [
            'assignments' => $query->latest()->paginate(10),
            'providers' => ProviderDetails::with('user', 'specialty')->orderByDesc('created_at')->get(),
            'practices' => Practice::orderBy('legal_name')->get(),
        ]);
    }
}
