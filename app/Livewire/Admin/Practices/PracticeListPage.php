<?php

namespace App\Livewire\Admin\Practices;

use App\Enums\PracticeStatus;
use App\Models\Practice;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin', ['title' => 'Practices'])]
class PracticeListPage extends Component
{
    use WithPagination;

    public $search = '';
    public $filterStatus = '';
    public $practiceId = null;

    public function updated($propertyName)
    {
        if (in_array($propertyName, ['search', 'filterStatus'])) {
            $this->resetPage();
        }
    }

    public function delete(int $id): void
    {
        $this->practiceId = $id;
        sweetalert()
            ->showDenyButton()
            ->info('Are you sure you want to delete this practice?');
    }

    #[On('sweetalert:confirmed')]
    public function onConfirmed(array $payload): void
    {
        $practice = Practice::with('user')->findOrFail($this->practiceId);
        $user = $practice->user;

        $practice->delete();
        $user?->delete();

        $this->practiceId = null;
        flash()->info('Practice successfully deleted.');
    }

    #[On('sweetalert:denied')]
    public function onDeny(array $payload): void
    {
        $this->practiceId = null;
        flash()->info('Deletion cancelled.');
    }

    public function render()
    {
        $query = Practice::with('primaryAddress');

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->search) {
            $search = '%' . $this->search . '%';

            $query->where(function ($q) use ($search) {
                $q->where('legal_name', 'like', $search)
                    ->orWhere('dba_name', 'like', $search)
                    ->orWhere('ein_tin', 'like', $search)
                    ->orWhere('group_npi', 'like', $search)
                    ->orWhere('taxonomy_code', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhereHas('addresses', function ($addressQuery) use ($search) {
                        $addressQuery->where('city', 'like', $search)
                            ->orWhere('state', 'like', $search)
                            ->orWhere('zip_code', 'like', $search);
                    });
            });
        }

        $practices = $query->latest()->paginate(10);

        $stats = [
            'total' => Practice::count(),
            'active' => Practice::where('status', PracticeStatus::ACTIVE->value)->count(),
            'pending' => Practice::where('status', PracticeStatus::PENDING->value)->count(),
            'inactive' => Practice::where('status', PracticeStatus::INACTIVE->value)->count(),
        ];

        return view('livewire.admin.practices.practice-list-page', compact('practices', 'stats'));
    }
}
