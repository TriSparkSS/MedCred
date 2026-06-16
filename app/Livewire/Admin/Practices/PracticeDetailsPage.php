<?php

namespace App\Livewire\Admin\Practices;

use App\Models\Practice;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'Practice Details'])]
class PracticeDetailsPage extends Component
{
    public Practice $practice;

    public function mount($practice)
    {
        $this->practice = Practice::with('user', 'addresses')->findOrFail($practice);
    }

    public function render()
    {
        return view('livewire.admin.practices.practice-details-page');
    }
}
