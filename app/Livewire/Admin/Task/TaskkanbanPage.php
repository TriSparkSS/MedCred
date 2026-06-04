<?php

namespace App\Livewire\Admin\Task;

use Livewire\Component;
use Livewire\Attributes\Layout;

#[Layout('layouts::admin', ['title' => 'Task Kanban'])]
class TaskkanbanPage extends Component
{
    public function render()
    {
        return view('livewire.admin.task.taskkanban-page');
    }
}
