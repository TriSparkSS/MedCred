<?php

namespace App\Livewire\Admin\Reports;

use App\Services\ReportExportService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'Reports'])]
class ReportListPage extends Component
{
    public $search = '';

    #[Url(as: 'report', history: true)]
    public string $activeReport = '';

    #[Url(as: 'delay_tab', history: true)]
    public string $delayDimension = 'by_owner';

    public function viewReport(string $key): void
    {
        if (array_key_exists($key, ReportExportService::definitions())) {
            $this->activeReport = $key;
        }
    }

    public function closeReport(): void
    {
        $this->activeReport = '';
    }

    public function setDelayDimension(string $dim): void
    {
        if (in_array($dim, ['by_owner', 'by_team', 'by_provider', 'by_payer'], true)) {
            $this->delayDimension = $dim;
        }
    }

    public function render(ReportExportService $exportService)
    {
        $definitions = ReportExportService::definitions();

        $reports = collect($definitions)
            ->when($this->search, function ($collection) {
                $term = strtolower($this->search);

                return $collection->filter(function ($report) use ($term) {
                    return str_contains(strtolower($report['name']), $term)
                        || str_contains(strtolower($report['description']), $term)
                        || str_contains(strtolower($report['type']), $term);
                });
            });

        $activeReportData = null;
        $activeReportMeta = null;
        if ($this->activeReport !== '' && isset($definitions[$this->activeReport])) {
            $activeReportMeta = $definitions[$this->activeReport];
            $activeReportData = $exportService->rowsFor($this->activeReport);
        }

        return view('livewire.admin.reports.report-list-page', [
            'reports' => $reports,
            'reportCount' => count($definitions),
            'canExport' => Auth::guard('admin')->user()?->can('admin.reports.export') ?? false,
            'activeReportData' => $activeReportData,
            'activeReportMeta' => $activeReportMeta,
            'delayBreakdown' => $exportService->delayManagementBreakdown(),
            'approvalSummary' => $exportService->approvalDenialSummary(),
        ]);
    }
}
