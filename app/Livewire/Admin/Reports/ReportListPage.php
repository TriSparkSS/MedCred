<?php

namespace App\Livewire\Admin\Reports;

use App\Services\ReportExportService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'Reports'])]
class ReportListPage extends Component
{
    public $search = '';

    public function render()
    {
        $reports = collect(ReportExportService::definitions())
            ->when($this->search, function ($collection) {
                $term = strtolower($this->search);

                return $collection->filter(function ($report, $key) use ($term) {
                    return str_contains(strtolower($report['name']), $term)
                        || str_contains(strtolower($report['description']), $term)
                        || str_contains(strtolower($report['type']), $term);
                });
            });

        return view('livewire.admin.reports.report-list-page', [
            'reports' => $reports,
            'reportCount' => count(ReportExportService::definitions()),
        ]);
    }
}
