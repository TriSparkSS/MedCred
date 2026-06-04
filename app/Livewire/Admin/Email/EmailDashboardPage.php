<?php

namespace App\Livewire\Admin\Email;

use Livewire\Component;

use Livewire\Attributes\Layout;

#[Layout('layouts::admin', ['title' => 'Email Dashboard'])]
class EmailDashboardPage extends Component
{
    public function render()
    {
        return view('livewire.admin.email.email-dashboard-page');
    }
}
