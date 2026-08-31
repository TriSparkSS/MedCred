<?php

namespace App\Livewire\Admin\Reports;

use App\Mail\ReportCsvMail;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Services\AdminScopeService;
use App\Services\MailSettingsService;
use App\Services\ReportExportService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts::admin', ['title' => 'Total Credentialing Report by Practice'])]
class PracticeCredentialingReportPage extends Component
{
    public const PRACTICES_PER_PAGE = 4;

    public const PREVIEW_PER_PRACTICE = 5;

    public string $draftDateFrom = '';

    public string $draftDateTo = '';

    public string $draftPracticeId = '';

    public string $draftPayerId = '';

    public string $draftState = '';

    public string $draftProviderId = '';

    public string $appliedDateFrom = '';

    public string $appliedDateTo = '';

    public string $appliedPracticeId = '';

    public string $appliedPayerId = '';

    public string $appliedState = '';

    public string $appliedProviderId = '';

    public int $page = 1;

    /** @var list<int> */
    public array $collapsedPracticeIds = [];

    public bool $showEmailModal = false;

    public string $emailTo = '';

    public function updatedDraftPracticeId(): void
    {
        $this->draftProviderId = '';
    }

    public function runReport(): void
    {
        $this->appliedDateFrom = $this->draftDateFrom;
        $this->appliedDateTo = $this->draftDateTo;
        $this->appliedPracticeId = $this->draftPracticeId;
        $this->appliedPayerId = $this->draftPayerId;
        $this->appliedState = $this->draftState;
        $this->appliedProviderId = $this->draftProviderId;
        $this->page = 1;
        $this->collapsedPracticeIds = [];
    }

    public function gotoPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function togglePractice(int $practiceId): void
    {
        if (in_array($practiceId, $this->collapsedPracticeIds, true)) {
            $this->collapsedPracticeIds = array_values(array_filter(
                $this->collapsedPracticeIds,
                fn (int $id) => $id !== $practiceId
            ));

            return;
        }

        $this->collapsedPracticeIds[] = $practiceId;
    }

    public function openEmailModal(): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.reports.export'), 403);

        $this->emailTo = Auth::guard('admin')->user()?->email ?? '';
        $this->showEmailModal = true;
        $this->resetValidation();
    }

    public function closeEmailModal(): void
    {
        $this->showEmailModal = false;
        $this->emailTo = '';
        $this->resetValidation();
    }

    public function emailReport(ReportExportService $reports, MailSettingsService $mailSettings): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.reports.export'), 403);

        $this->validate([
            'emailTo' => 'required|email',
        ]);

        try {
            $mailSettings->applyToConfig();
            $mailSettings->assertConfigured();

            $filters = $this->appliedFilters();
            $csv = $reports->csvContents('practice_credentialing_status', $filters);
            $data = $reports->rowsFor('practice_credentialing_status', $filters);

            Mail::to($this->emailTo)->send(new ReportCsvMail(
                'Total Credentialing Report by Practice',
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

    public function downloadCsv(ReportExportService $reports): StreamedResponse
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.reports.export'), 403);

        $data = $reports->practiceCredentialingStatusData($this->appliedFilters());

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $data['headers']);
            foreach ($data['rows'] as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $data['filename'], [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @return array{practice_id?: string, payer_id?: string, provider_id?: string, state?: string, date_from?: string, date_to?: string}
     */
    public function appliedFilters(): array
    {
        return array_filter([
            'practice_id' => $this->appliedPracticeId,
            'payer_id' => $this->appliedPayerId,
            'provider_id' => $this->appliedProviderId,
            'state' => $this->appliedState,
            'date_from' => $this->appliedDateFrom,
            'date_to' => $this->appliedDateTo,
        ], fn ($value) => filled($value));
    }

    public function render(ReportExportService $reports, AdminScopeService $scope)
    {
        $admin = Auth::guard('admin')->user();

        $report = $reports->practiceCredentialingReport(
            $this->appliedFilters(),
            $this->page,
            self::PRACTICES_PER_PAGE,
            self::PREVIEW_PER_PRACTICE,
        );

        $practiceQuery = Practice::query()->orderBy('legal_name');
        if ($admin) {
            $scope->scopePractices($practiceQuery, $admin);
        }
        $practices = $practiceQuery->get(['id', 'legal_name', 'client_code']);

        $payers = Payer::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        $providers = $this->draftPracticeId
            ? ProviderDetails::query()
                ->whereHas('practices', fn ($q) => $q->where('practices.id', (int) $this->draftPracticeId))
                ->with('user:id,name')
                ->get()
            : collect();

        $states = \App\Models\CredentialingCase::query()
            ->when($admin, fn ($q) => $scope->scopeCredentialingCases($q, $admin))
            ->whereNotNull('state')
            ->where('state', '!=', '')
            ->distinct()
            ->orderBy('state')
            ->pluck('state');

        return view('livewire.admin.reports.practice-credentialing-report-page', [
            'report' => $report,
            'practices' => $practices,
            'payers' => $payers,
            'providers' => $providers,
            'states' => $states,
            'generatedBy' => $admin?->name ?? 'Admin',
            'reportDate' => now()->timezone('America/New_York')->format('M j, Y g:i A'),
            'canExport' => $admin?->can('admin.reports.export') ?? false,
            'reportsService' => $reports,
        ]);
    }
}
