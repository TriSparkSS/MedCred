<?php

namespace App\Livewire\Admin\Practices;

use Livewire\Component;
use Livewire\Attributes\Layout;

#[Layout('layouts::admin', ['title' => 'Providers'])]
class PracticeListPage extends Component
{
    public function render()
    {
        return view('livewire.admin.practices.practice-list-page');
    }
}
