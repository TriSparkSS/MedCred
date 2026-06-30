<?php

namespace App\Livewire\Admin\Payer;

use App\Models\Payer;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin', ['title' => 'Payers'])]
class PayerListPage extends Component
{
    use WithPagination;

    public $search = '';

    public $filterActive = '';

    public $payerId = null;

    public function updated($propertyName): void
    {
        if (in_array($propertyName, ['search', 'filterActive'])) {
            $this->resetPage();
        }
    }

    public function delete(int $id): void
    {
        $this->payerId = $id;
        sweetalert()
            ->showDenyButton()
            ->info('Are you sure you want to delete this payer?');
    }

    #[On('sweetalert:confirmed')]
    public function onConfirmed(array $payload): void
    {
        Payer::findOrFail($this->payerId)->delete();
        $this->payerId = null;
        flash()->info('Payer successfully deleted.');
    }

    #[On('sweetalert:denied')]
    public function onDeny(array $payload): void
    {
        $this->payerId = null;
        flash()->info('Deletion cancelled.');
    }

    public function render()
    {
        $query = Payer::query();

        if ($this->filterActive === 'active') {
            $query->where('is_active', true);
        } elseif ($this->filterActive === 'inactive') {
            $query->where('is_active', false);
        }

        if ($this->search) {
            $search = '%' . $this->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('states_applicable', 'like', $search)
                    ->orWhere('email', 'like', $search);
            });
        }

        $payers = $query->orderBy('name')->paginate(10);

        $stats = [
            'total' => Payer::count(),
            'active' => Payer::where('is_active', true)->count(),
            'inactive' => Payer::where('is_active', false)->count(),
        ];

        return view('livewire.admin.payer.payer-list-page', compact('payers', 'stats'));
    }
}
