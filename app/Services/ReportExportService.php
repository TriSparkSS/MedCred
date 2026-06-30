<?php

namespace App\Services;

use App\Models\CredentialingCase;
use App\Models\Document;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    public static function definitions(): array
    {
        return [
            'open_applications' => [
                'name' => 'Open Applications',
                'description' => 'Active credentialing cases by status, payer, state, and assigned owner.',
                'type' => 'Operational',
            ],
            'document_compliance' => [
                'name' => 'Document Compliance',
                'description' => 'Checklist completion percentage per credentialing case.',
                'type' => 'Compliance',
            ],
            'case_aging' => [
                'name' => 'Case Aging',
                'description' => 'Days in process for active credentialing applications.',
                'type' => 'Operational',
            ],
            'expiring_documents' => [
                'name' => 'Expiring Documents',
                'description' => 'Provider and case documents expiring within 30 days.',
                'type' => 'Compliance',
            ],
            'productivity_by_executive' => [
                'name' => 'Productivity by Executive',
                'description' => 'Active cases, approvals, and average turnaround per assigned admin.',
                'type' => 'Analytics',
            ],
            'turnaround_by_payer' => [
                'name' => 'Turnaround by Payer',
                'description' => 'Average days from submission to effective date by payer.',
                'type' => 'Analytics',
            ],
            'recredentialing_upcoming' => [
                'name' => 'Upcoming Recredentialing',
                'description' => 'Cases with revalidation due within 90 days.',
                'type' => 'Compliance',
            ],
        ];
    }

    public function export(string $type): StreamedResponse
    {
        return match ($type) {
            'open_applications' => $this->exportOpenApplications(),
            'document_compliance' => $this->exportDocumentCompliance(),
            'case_aging' => $this->exportCaseAging(),
            'expiring_documents' => $this->exportExpiringDocuments(),
            'productivity_by_executive' => $this->exportProductivityByExecutive(),
            'turnaround_by_payer' => $this->exportTurnaroundByPayer(),
            'recredentialing_upcoming' => $this->exportRecredentialingUpcoming(),
            default => abort(404, 'Report not found.'),
        };
    }

    protected function exportOpenApplications(): StreamedResponse
    {
        $cases = CredentialingCase::active()
            ->with(['provider.user', 'payer', 'status', 'assignedAdmin', 'delayOwner', 'practice'])
            ->orderBy('case_number')
            ->get();

        return $this->streamCsv('open_applications_' . now()->format('Ymd') . '.csv', [
            'Case Number', 'Provider', 'NPI', 'Payer', 'Practice', 'State', 'Status',
            'Assigned To', 'Delay Owner', 'Intake Date', 'Next Follow-up',
        ], $cases->map(fn ($case) => [
            $case->case_number,
            $case->provider->user->name ?? '',
            $case->provider->npi ?? '',
            $case->payer->name ?? '',
            $case->practice->legal_name ?? '',
            $case->state ?? '',
            $case->status->name ?? '',
            $case->assignedAdmin->name ?? '',
            $case->delayOwner->name ?? '',
            $case->intake_date?->format('Y-m-d') ?? '',
            $case->next_follow_up_date?->format('Y-m-d') ?? '',
        ]));
    }

    protected function exportDocumentCompliance(): StreamedResponse
    {
        $cases = CredentialingCase::with([
            'provider.user', 'payer', 'documentItems',
        ])->orderBy('case_number')->get();

        return $this->streamCsv('document_compliance_' . now()->format('Ymd') . '.csv', [
            'Case Number', 'Provider', 'Payer', 'Required Docs', 'Received', 'Completion %',
        ], $cases->map(function ($case) {
            $checklist = $case->checklist_completion;

            return [
                $case->case_number,
                $case->provider->user->name ?? '',
                $case->payer->name ?? '',
                $checklist['total'],
                $checklist['received'],
                $checklist['percent'],
            ];
        }));
    }

    protected function exportCaseAging(): StreamedResponse
    {
        $cases = CredentialingCase::active()
            ->with(['provider.user', 'payer', 'status'])
            ->orderByDesc('intake_date')
            ->get();

        return $this->streamCsv('case_aging_' . now()->format('Ymd') . '.csv', [
            'Case Number', 'Provider', 'Payer', 'Status', 'Intake Date', 'Submission Date', 'Aging Days',
        ], $cases->map(fn ($case) => [
            $case->case_number,
            $case->provider->user->name ?? '',
            $case->payer->name ?? '',
            $case->status->name ?? '',
            $case->intake_date?->format('Y-m-d') ?? '',
            $case->submission_date?->format('Y-m-d') ?? '',
            $case->aging_days,
        ]));
    }

    protected function exportExpiringDocuments(): StreamedResponse
    {
        $documents = Document::expiringSoon(30)
            ->with(['provider.user', 'documentType', 'credentialingCase'])
            ->orderBy('expiry_date')
            ->get();

        return $this->streamCsv('expiring_documents_' . now()->format('Ymd') . '.csv', [
            'Title', 'Type', 'Provider', 'Case Number', 'Expiry Date', 'State',
        ], $documents->map(fn ($doc) => [
            $doc->title,
            $doc->documentType->name ?? '',
            $doc->provider->user->name ?? '',
            $doc->credentialingCase->case_number ?? '',
            $doc->expiry_date?->format('Y-m-d') ?? '',
            $doc->state ?? '',
        ]));
    }

    protected function exportProductivityByExecutive(): StreamedResponse
    {
        $rows = app(ProductivityDashboardService::class)->executiveMetrics();

        return $this->streamCsv('productivity_by_executive_' . now()->format('Ymd') . '.csv', [
            'Executive', 'Active Cases', 'Approved', 'Overdue', 'Open Tasks', 'Avg Turnaround Days',
        ], $rows->map(fn ($r) => [
            $r['name'], $r['active_cases'], $r['approved_cases'], $r['overdue_cases'], $r['open_tasks'], $r['avg_turnaround_days'] ?? '',
        ]));
    }

    protected function exportTurnaroundByPayer(): StreamedResponse
    {
        $rows = app(ProductivityDashboardService::class)->payerTurnaround();

        return $this->streamCsv('turnaround_by_payer_' . now()->format('Ymd') . '.csv', [
            'Payer', 'Approved Count', 'Avg Days',
        ], $rows->map(fn ($r) => [$r['payer'], $r['approved_count'], $r['avg_days']]));
    }

    protected function exportRecredentialingUpcoming(): StreamedResponse
    {
        $cases = app(ProductivityDashboardService::class)->recredentialingUpcoming();

        return $this->streamCsv('recredentialing_upcoming_' . now()->format('Ymd') . '.csv', [
            'Case Number', 'Provider', 'Payer', 'Revalidation Due',
        ], $cases->map(fn ($case) => [
            $case->case_number,
            $case->provider->user->name ?? '',
            $case->payer->name ?? '',
            $case->revalidation_due_date?->format('Y-m-d') ?? '',
        ]));
    }

    protected function streamCsv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, is_array($row) ? $row : $row->toArray());
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
