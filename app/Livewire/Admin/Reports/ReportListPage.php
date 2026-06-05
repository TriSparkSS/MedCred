<?php

namespace App\Livewire\Admin\Reports;

use Livewire\Component;
use Livewire\Attributes\Layout;

#[Layout('layouts::admin', ['title' => 'Reports'])]
class ReportListPage extends Component
{
    public function render()
    {
        return view('livewire.admin.reports.report-list-page');
    }
}
