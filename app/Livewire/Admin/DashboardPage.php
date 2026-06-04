<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'Dashboard'])]
class DashboardPage extends Component
{
    public function render()
    {
        return view('livewire.admin.dashboard-page');
    }
}
