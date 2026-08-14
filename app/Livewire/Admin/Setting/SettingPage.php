<?php

namespace App\Livewire\Admin\Setting;

use Livewire\Component;
use Livewire\Attributes\Layout;

#[Layout('layouts::admin', ['title' => 'Settings'])]
class SettingPage extends Component
{
    public function render()
    {
        return view('livewire.admin.setting.setting-page');
    }
}
