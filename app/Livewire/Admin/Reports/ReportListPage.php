<?php

namespace App\Livewire\Admin\Reports;

use App\Mail\ReportCsvMail;
use App\Services\MailSettingsService;
use App\Services\ReportExportService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'Reports'])]
class ReportListPage extends Component
{
    public $search = '';

    public bool $showEmailModal = false;

    public string $emailReportKey = '';

    public string $emailTo = '';

    public function openEmailModal(string $key): void
    {
        $definitions = ReportExportService::definitions();
        if (! isset($definitions[$key]) || empty($definitions[$key]['emailable'])) {
            flash()->error('This report cannot be emailed.');

            return;
        }

        $this->emailReportKey = $key;
        $this->emailTo = Auth::guard('admin')->user()?->email ?? '';
        $this->showEmailModal = true;
    }

    public function closeEmailModal(): void
    {
        $this->showEmailModal = false;
        $this->emailReportKey = '';
        $this->emailTo = '';
        $this->resetValidation();
    }

    public function emailReport(ReportExportService $reports, MailSettingsService $mailSettings): void
    {
        $this->validate([
            'emailTo' => 'required|email',
            'emailReportKey' => 'required|string',
        ]);

        $definitions = ReportExportService::definitions();
        if (! isset($definitions[$this->emailReportKey]) || empty($definitions[$this->emailReportKey]['emailable'])) {
            flash()->error('This report cannot be emailed.');

            return;
        }

        try {
            $mailSettings->applyToConfig();
            $mailSettings->assertConfigured();

            $csv = $reports->csvContents($this->emailReportKey);
            $data = $reports->rowsFor($this->emailReportKey);
            $reportName = $definitions[$this->emailReportKey]['name'];

            Mail::to($this->emailTo)->send(new ReportCsvMail(
                $reportName,
                $csv,
                $data['filename'],
                $mailSettings->resolveFromAddress(),
                $mailSettings->resolveFromName(),
            ));

            flash()->success('Report emailed to '.$this->emailTo);
            $this->closeEmailModal();
        } catch (\Throwable $e) {
            flash()->error('Failed to email report: '.$e->getMessage());
        }
    }

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
            'canExport' => Auth::guard('admin')->user()?->can('admin.reports.export') ?? false,
        ]);
    }
}
