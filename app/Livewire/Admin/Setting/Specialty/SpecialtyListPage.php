<?php

namespace App\Livewire\Admin\Setting\Specialty;

use Livewire\Component;

use Livewire\Attributes\Layout;

#[Layout('layouts::admin', ['title' => 'Specialties | Settings'])]
class SpecialtyListPage extends Component
{
    public function render()
    {
        return view('livewire.admin.setting.specialty.specialty-list-page');
    }
}
