<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CredentialingCase;
use App\Models\Document;
use App\Models\EmailMessage;
use App\Support\Branding;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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
            'practice_credentialing_status' => [
                'name' => 'Total Credentialing Report by Practice',
                'description' => 'Payer credentialing status and latest comments by practice and provider.',
                'type' => 'Operational',
                'link' => route('admin.reports.practice-credentialing'),
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
                'link' => route('admin.credentials', ['revalidation' => '90']),
            ],
            'email_operations' => [
                'name' => 'Email Operations',
                'description' => 'Unlinked messages, pending attachments, and failed deliveries.',
                'type' => 'Operational',
                'link' => route('admin.email.dashboard', ['filter' => 'unlinked']),
            ],
            'delay_ownership' => [
                'name' => 'Delay Ownership',
                'description' => 'Active cases grouped by delay owner and executive.',
                'type' => 'Operational',
                'link' => route('admin.credentials'),
            ],
            'approval_denial_trends' => [
                'name' => 'Approval & Denial Trends',
                'description' => 'Approval rates, denied counts, and turnaround breakdown by payer and state.',
                'type' => 'Analytics',
            ],
            'panel_closed_summary' => [
                'name' => 'Panel-Closed & Rejection Report',
                'description' => 'Applications closed due to closed payer panels or denials by payer, state, and specialty.',
                'type' => 'Analytics',
            ],
        ];
    }

    public function export(string $type, string $format = 'csv', array $filters = []): StreamedResponse
    {
        if (! array_key_exists($type, self::definitions())) {
            abort(404, 'Report not found.');
        }

        $normalizedFormat = strtolower(trim($format ?: 'csv'));
        if ($normalizedFormat === 'excel') {
            $normalizedFormat = 'xlsx';
        }
        if (! in_array($normalizedFormat, ['csv', 'xlsx', 'pdf'], true)) {
            $normalizedFormat = 'csv';
        }

        AuditLog::record('report.exported', null, auth('admin')->id(), [
            'report_type' => $type,
            'format' => $normalizedFormat,
        ]);

        if ($normalizedFormat === 'xlsx') {
            return $this->exportXlsx($type, $filters);
        }

        if ($normalizedFormat === 'pdf') {
            return $this->exportPdf($type, $filters);
        }

        return match ($type) {
            'open_applications' => $this->exportOpenApplications(),
            'practice_credentialing_status' => $this->exportPracticeCredentialingStatus($filters),
            'document_compliance' => $this->exportDocumentCompliance(),
            'case_aging' => $this->exportCaseAging(),
            'expiring_documents' => $this->exportExpiringDocuments(),
            'productivity_by_executive' => $this->exportProductivityByExecutive(),
            'turnaround_by_payer' => $this->exportTurnaroundByPayer(),
            'recredentialing_upcoming' => $this->exportRecredentialingUpcoming(),
            'email_operations' => $this->exportEmailOperations(),
            'delay_ownership' => $this->exportDelayOwnership(),
            'approval_denial_trends' => $this->exportApprovalDenialTrends(),
            'panel_closed_summary' => $this->exportPanelClosedSummary(),
            default => abort(404, 'Report not found.'),
        };
    }

    /**
     * @param  array{practice_id?: string|int|null, payer_id?: string|int|null, provider_id?: string|int|null, state?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     */
    public function exportXlsx(string $type, array $filters = []): StreamedResponse
    {
        if (! array_key_exists($type, self::definitions())) {
            abort(404, 'Report not found.');
        }

        if ($type === 'practice_credentialing_status') {
            $filename = 'total_credentialing_report_by_practice_'.now()->format('Ymd').'.xlsx';
            $contents = $this->buildPracticeCredentialingSpreadsheet($filters);
        } else {
            $data = $this->rowsFor($type, $filters);
            $filename = preg_replace('/\.csv$/i', '.xlsx', $data['filename']) ?: ($type.'_'.now()->format('Ymd').'.xlsx');
            $contents = $this->buildGenericSpreadsheet($type, $data);
        }

        return response()->streamDownload(function () use ($contents) {
            echo $contents;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array{practice_id?: string|int|null, payer_id?: string|int|null, provider_id?: string|int|null, state?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     */
    public function exportPdf(string $type, array $filters = []): StreamedResponse
    {
        if (! array_key_exists($type, self::definitions())) {
            abort(404, 'Report not found.');
        }

        $data = $this->rowsFor($type, $filters);
        $filename = preg_replace('/\.csv$/i', '.pdf', $data['filename']) ?: ($type.'_'.now()->format('Ymd').'.pdf');
        $contents = $this->pdfContents($type, $filters);

        return response()->streamDownload(function () use ($contents) {
            echo $contents;
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * @param  array{practice_id?: string|int|null, payer_id?: string|int|null, provider_id?: string|int|null, state?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     * @return array{filename: string, headers: array<int, string>, rows: array<int, array<int, string|int|float|null>>}
     */
    public function rowsFor(string $type, array $filters = []): array
    {
        return match ($type) {
            'open_applications' => $this->openApplicationsData(),
            'practice_credentialing_status' => $this->practiceCredentialingStatusData($filters),
            'document_compliance' => $this->documentComplianceData(),
            'case_aging' => $this->caseAgingData(),
            'expiring_documents' => $this->expiringDocumentsData(),
            'productivity_by_executive' => $this->productivityByExecutiveData(),
            'turnaround_by_payer' => $this->turnaroundByPayerData(),
            'recredentialing_upcoming' => $this->recredentialingUpcomingData(),
            'email_operations' => $this->emailOperationsData(),
            'delay_ownership' => $this->delayOwnershipData(),
            'approval_denial_trends' => $this->approvalDenialTrendsData(),
            'panel_closed_summary' => $this->panelClosedSummaryData(),
            default => throw new \InvalidArgumentException("Export is not supported for report [{$type}]."),
        };
    }

    /**
     * @param  array{practice_id?: string|int|null, payer_id?: string|int|null, provider_id?: string|int|null, state?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     */
    public function csvContents(string $type, array $filters = []): string
    {
        $data = $this->rowsFor($type, $filters);

        return $this->toCsv($data['headers'], $data['rows']);
    }

    public function toCsv(array $headers, iterable $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $this->sanitizeCsvRow($headers));
        foreach ($rows as $row) {
            $rowArray = is_array($row) ? $row : $row->toArray();
            fputcsv($handle, $this->sanitizeCsvRow($rowArray));
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    /**
     * @param  array{practice_id?: string|int|null, payer_id?: string|int|null, provider_id?: string|int|null, state?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     */
    public function xlsxContents(string $type, array $filters = []): string
    {
        if (! array_key_exists($type, self::definitions())) {
            throw new \InvalidArgumentException("Excel export is not supported for report [{$type}].");
        }

        if ($type === 'practice_credentialing_status') {
            return $this->buildPracticeCredentialingSpreadsheet($filters);
        }

        $data = $this->rowsFor($type, $filters);

        return $this->buildGenericSpreadsheet($type, $data);
    }

    /**
     * @param  array{practice_id?: string|int|null, payer_id?: string|int|null, provider_id?: string|int|null, state?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     */
    public function pdfContents(string $type, array $filters = []): string
    {
        if (! array_key_exists($type, self::definitions())) {
            throw new \InvalidArgumentException("PDF export is not supported for report [{$type}].");
        }

        $meta = self::definitions()[$type];
        $data = $this->rowsFor($type, $filters);

        $title = e((string) ($meta['name'] ?? $type));
        $description = e((string) ($meta['description'] ?? ''));
        $generatedAt = e(now()->timezone('America/New_York')->format('M j, Y g:i A').' ET');

        $headerCells = '';
        foreach ($data['headers'] as $header) {
            $headerCells .= '<th>'.e((string) $header).'</th>';
        }

        $bodyRows = '';
        foreach ($data['rows'] as $rowIndex => $row) {
            $bg = $rowIndex % 2 === 1 ? '#f5f7fa' : '#ffffff';
            $bodyRows .= "<tr style=\"background-color: {$bg};\">";
            foreach ($row as $cell) {
                $bodyRows .= '<td>'.e((string) ($cell ?? '')).'</td>';
            }
            $bodyRows .= '</tr>';
        }

        if ($bodyRows === '') {
            $colCount = max(1, count($data['headers']));
            $bodyRows = "<tr><td colspan=\"{$colCount}\" style=\"text-align: center; color: #6b7280;\">No records found for this report.</td></tr>";
        }

        $logo = Branding::pdfLogoDataUri();
        $logoBlock = $logo !== '' ? "<div class=\"brand\"><img src=\"{$logo}\" alt=\"MedCred\"></div>" : '';

        $html = <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>{$title}</title>
            <style>
                body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; margin: 16px; }
                .brand { margin-bottom: 8px; }
                .brand img { height: 42px; width: auto; }
                .header { background-color: #163b65; color: #ffffff; padding: 12px 16px; margin-bottom: 10px; }
                .header h1 { margin: 0 0 4px 0; font-size: 16px; }
                .header p { margin: 0; font-size: 9px; color: #dbeafe; }
                table { width: 100%; border-collapse: collapse; }
                th { background-color: #163b65; color: #ffffff; text-align: left; padding: 6px 8px; font-size: 8.5px; border: 1px solid #cbd5e1; }
                td { padding: 5px 8px; border: 1px solid #e2e8f0; vertical-align: top; word-wrap: break-word; }
            </style>
        </head>
        <body>
            {$logoBlock}
            <div class="header">
                <h1>{$title}</h1>
                <p>{$description} | Generated {$generatedAt}</p>
            </div>
            <table>
                <thead><tr>{$headerCells}</tr></thead>
                <tbody>{$bodyRows}</tbody>
            </table>
        </body>
        </html>
        HTML;

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'landscape');
        $dompdf->render();

        return $dompdf->output() ?: '';
    }

    /**
     * @param  array{filename: string, headers: array<int, string>, rows: array<int, array<int, mixed>>}  $data
     */
    protected function buildGenericSpreadsheet(string $type, array $data): string
    {
        $meta = self::definitions()[$type] ?? ['name' => $type, 'description' => ''];
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr(preg_replace('/[\\\\\/\?\*\:\[\]]/', '', (string) ($meta['name'] ?? 'Report')), 0, 31) ?: 'Report');

        $colCount = max(1, count($data['headers']));
        $lastCol = Coordinate::stringFromColumnIndex($colCount);
        $navy = $this->excelTitleFillColor();
        $zebra = $this->excelZebraFillColor();

        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', (string) ($meta['name'] ?? $type));
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $navy]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A2', ((string) ($meta['description'] ?? '')).' | Generated '.now()->timezone('America/New_York')->format('M j, Y g:i A').' ET');
        $sheet->getStyle("A2:{$lastCol}2")->applyFromArray([
            'font' => ['size' => 9, 'color' => ['rgb' => '4B5563']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $this->excelSubtitleFillColor()]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(18);

        $headerRow = 4;
        $sheet->fromArray($this->sanitizeCsvRow($data['headers']), null, "A{$headerRow}");
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $navy]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(24);

        $rowNum = $headerRow + 1;
        foreach (array_values($data['rows']) as $idx => $rowValues) {
            $rowArray = is_array($rowValues) ? array_values($rowValues) : $rowValues->values()->all();
            $sheet->fromArray([$this->sanitizeCsvRow($rowArray)], null, "A{$rowNum}");
            $rowFill = $idx % 2 === 1 ? $zebra : 'FFFFFF';
            $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->applyFromArray([
                'font' => ['size' => 9, 'color' => ['rgb' => '1F2937']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowFill]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ]);
            $rowNum++;
        }

        for ($i = 1; $i <= $colCount; $i++) {
            $colLetter = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($colLetter)->setWidth(20);
        }

        $path = tempnam(sys_get_temp_dir(), 'rptxlsx');
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $contents = file_get_contents($path) ?: '';
        @unlink($path);
        $spreadsheet->disconnectWorksheets();

        return $contents;
    }

    /**
     * @param  array{practice_id?: string|int|null, payer_id?: string|int|null, provider_id?: string|int|null, state?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     */
    public function streamPracticeCredentialingXlsx(array $filters = []): StreamedResponse
    {
        AuditLog::record('report.exported', null, auth('admin')->id(), ['report_type' => 'practice_credentialing_xlsx', 'format' => 'xlsx']);

        $filename = 'total_credentialing_report_by_practice_'.now()->format('Ymd').'.xlsx';
        $contents = $this->buildPracticeCredentialingSpreadsheet($filters);

        return response()->streamDownload(function () use ($contents) {
            echo $contents;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array{practice_id?: string|int|null, payer_id?: string|int|null, provider_id?: string|int|null, state?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     */
    public function practiceCredentialingExcelPayload(array $filters = []): array
    {
        $cases = $this->loadPracticeCredentialingCases($filters);
        $buckets = $this->buildStatusBuckets($cases);
        $grouped = $cases->groupBy(fn (CredentialingCase $case) => (int) ($case->practice_id ?? 0));

        $practiceIds = $grouped->keys()
            ->filter(fn ($id) => (int) $id > 0)
            ->sortBy(fn ($id) => strtolower((string) ($grouped[$id]->first()?->practice->legal_name ?? '')))
            ->values();

        $groups = [];
        foreach ($practiceIds as $index => $practiceId) {
            /** @var Collection<int, CredentialingCase> $practiceCases */
            $practiceCases = $grouped[$practiceId];
            $groups[] = [
                'practice_id' => (int) $practiceId,
                'practice_name' => (string) ($practiceCases->first()?->practice->legal_name ?? 'Unknown Practice'),
                'provider_count' => $practiceCases->pluck('provider_id')->unique()->filter()->count(),
                'enrollment_count' => $practiceCases->count(),
                'index' => $index + 1,
                'rows' => $practiceCases->map(fn (CredentialingCase $case) => $this->mapCaseRow($case))->all(),
            ];
        }

        return [
            'filename' => 'total_credentialing_report_by_practice_'.now()->format('Ymd').'.xlsx',
            'generated_at' => now()->timezone('America/New_York')->format('M j, Y g:i A').' ET',
            'totals' => [
                'practices' => $practiceIds->count(),
                'providers' => $cases->pluck('provider_id')->unique()->filter()->count(),
                'enrollments' => $cases->count(),
            ],
            'buckets' => $buckets,
            'groups' => $groups,
        ];
    }

    public function excelTitleFillColor(): string
    {
        return '163B65';
    }

    public function excelSubtitleFillColor(): string
    {
        return 'EEF4FB';
    }

    public function excelZebraFillColor(): string
    {
        return 'F5F7FA';
    }

    public function statusBucketFillColor(string $bucket): string
    {
        return $this->excelStatusChipStyle('', $bucket)[0];
    }

    protected function exportPracticeCredentialingStatus(array $filters = []): StreamedResponse
    {
        $data = $this->practiceCredentialingStatusData($filters);

        return $this->streamCsv($data['filename'], $data['headers'], $data['rows']);
    }

    /**
     * @param  array{practice_id?: string|int|null, payer_id?: string|int|null, provider_id?: string|int|null, state?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     * @return array{
     *     totals: array{practices: int, providers: int, enrollments: int},
     *     buckets: list<array{key: string, label: string, count: int, percent: float, color: string}>,
     *     groups: list<array{practice_id: int, practice_name: string, provider_count: int, enrollment_count: int, rows: list<array<string, mixed>>, has_more: bool}>,
     *     pagination: array{page: int, per_page: int, total_practices: int, last_page: int}
     * }
     */
    public function practiceCredentialingReport(
        array $filters = [],
        int $page = 1,
        int $perPage = 4,
        int $previewPerPractice = 5,
    ): array {
        $cases = $this->loadPracticeCredentialingCases($filters);
        $buckets = $this->buildStatusBuckets($cases);
        $grouped = $cases->groupBy(fn (CredentialingCase $case) => (int) ($case->practice_id ?? 0));

        $practiceIds = $grouped->keys()
            ->filter(fn ($id) => (int) $id > 0)
            ->sortBy(fn ($id) => strtolower((string) ($grouped[$id]->first()?->practice->legal_name ?? '')))
            ->values();

        $totalPractices = $practiceIds->count();
        $lastPage = max(1, (int) ceil($totalPractices / max(1, $perPage)));
        $page = max(1, min($page, $lastPage));
        $pagePracticeIds = $practiceIds->slice(($page - 1) * $perPage, $perPage)->values();

        $groups = [];
        foreach ($pagePracticeIds as $index => $practiceId) {
            /** @var Collection<int, CredentialingCase> $practiceCases */
            $practiceCases = $grouped[$practiceId];
            $providerCount = $practiceCases->pluck('provider_id')->unique()->filter()->count();
            $enrollmentCount = $practiceCases->count();
            $preview = $practiceCases->take($previewPerPractice)->map(fn (CredentialingCase $case) => $this->mapCaseRow($case))->all();

            $groups[] = [
                'practice_id' => (int) $practiceId,
                'practice_name' => (string) ($practiceCases->first()?->practice->legal_name ?? 'Unknown Practice'),
                'provider_count' => $providerCount,
                'enrollment_count' => $enrollmentCount,
                'rows' => $preview,
                'has_more' => $enrollmentCount > $previewPerPractice,
                'index' => (($page - 1) * $perPage) + $index + 1,
            ];
        }

        return [
            'totals' => [
                'practices' => $totalPractices,
                'providers' => $cases->pluck('provider_id')->unique()->filter()->count(),
                'enrollments' => $cases->count(),
            ],
            'buckets' => $buckets,
            'groups' => $groups,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total_practices' => $totalPractices,
                'last_page' => $lastPage,
            ],
        ];
    }

    /**
     * @param  array{practice_id?: string|int|null, payer_id?: string|int|null, provider_id?: string|int|null, state?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     * @return array{filename: string, headers: array<int, string>, rows: array<int, array<int, string|int|null>>}
     */
    public function practiceCredentialingStatusData(array $filters = []): array
    {
        $cases = $this->loadPracticeCredentialingCases($filters);

        $rows = $cases->map(function (CredentialingCase $case) {
            $mapped = $this->mapCaseRow($case);

            return [
                $mapped['practice_name'],
                $mapped['client_code'],
                $mapped['provider_name'],
                $mapped['npi'],
                $mapped['case_number'],
                $mapped['payer_name'],
                $mapped['state'],
                $mapped['status_name'],
                $mapped['effective_date'],
                $mapped['revalidation_due'],
                $mapped['latest_comment'],
                $mapped['last_updated'],
            ];
        })->all();

        return [
            'filename' => 'total_credentialing_report_by_practice_'.now()->format('Ymd').'.csv',
            'headers' => [
                'Practice', 'Client Code', 'Provider', 'NPI', 'Case Number',
                'Payer', 'State', 'Credentialing Status', 'Effective Date', 'Revalidation Due',
                'Latest Comment', 'Last Updated',
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array{practice_id?: string|int|null, payer_id?: string|int|null, provider_id?: string|int|null, state?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     * @return list<array<string, mixed>>
     */
    public function practiceCredentialingDataset(array $filters = []): array
    {
        return $this->loadPracticeCredentialingCases($filters)
            ->map(fn (CredentialingCase $case) => $this->mapCaseRow($case))
            ->all();
    }

    /**
     * @param  array{practice_id?: string|int|null, payer_id?: string|int|null, provider_id?: string|int|null, state?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     * @return Collection<int, CredentialingCase>
     */
    protected function loadPracticeCredentialingCases(array $filters = [])
    {
        $query = CredentialingCase::query()
            ->with([
                'practice',
                'provider.user',
                'payer',
                'status',
                'latestActivity',
            ]);

        $this->applyAdminScope($query);
        $this->applyPracticeCredentialingFilters($query, $filters);

        return $query
            ->get()
            ->sortBy([
                fn ($case) => strtolower((string) ($case->practice->legal_name ?? '')),
                fn ($case) => strtolower((string) ($case->provider->user->name ?? '')),
                fn ($case) => (string) $case->case_number,
            ])
            ->values();
    }

    /**
     * @param  Builder<CredentialingCase>  $query
     * @param  array{practice_id?: string|int|null, payer_id?: string|int|null, provider_id?: string|int|null, state?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     */
    protected function applyPracticeCredentialingFilters($query, array $filters): void
    {
        if (! empty($filters['practice_id'])) {
            $query->where('practice_id', (int) $filters['practice_id']);
        }
        if (! empty($filters['payer_id'])) {
            $query->where('payer_id', (int) $filters['payer_id']);
        }
        if (! empty($filters['provider_id'])) {
            $query->where('provider_id', (int) $filters['provider_id']);
        }
        if (! empty($filters['state'])) {
            $query->where('state', $filters['state']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('last_action_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('last_action_at', '<=', $filters['date_to']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapCaseRow(CredentialingCase $case): array
    {
        $latest = $case->relationLoaded('latestActivity')
            ? $case->latestActivity
            : ($case->latestActivity ?? $case->activities->first());
        $statusName = (string) ($case->status->name ?? '');
        $bucket = $this->resolveStatusBucket($case->status?->dashboard_category, $statusName);

        return [
            'case_id' => $case->id,
            'practice_id' => (int) $case->practice_id,
            'practice_name' => (string) ($case->practice->legal_name ?? ''),
            'client_code' => (string) ($case->practice->client_code ?? ''),
            'provider_name' => (string) ($case->provider->user->name ?? ''),
            'npi' => (string) ($case->provider->npi ?? ''),
            'case_number' => (string) $case->case_number,
            'payer_name' => (string) ($case->payer->name ?? ''),
            'state' => (string) ($case->state ?? ''),
            'status_name' => $statusName,
            'status_bucket' => $bucket,
            'effective_date' => $case->effective_date?->format('m/d/Y') ?? '',
            'revalidation_due' => $case->revalidation_due_date?->format('m/d/Y') ?? '',
            'latest_comment' => (string) ($latest?->summary ?? ''),
            'last_activity' => (string) ($latest?->summary ?? ''),
            'last_updated' => $case->last_action_at?->timezone('America/New_York')->format('m/d/Y h:i A')
                ?? $latest?->created_at?->timezone('America/New_York')->format('m/d/Y h:i A')
                ?? '',
        ];
    }

    /**
     * @param  Collection<int, CredentialingCase>  $cases
     * @return list<array{key: string, label: string, count: int, percent: float, color: string}>
     */
    protected function buildStatusBuckets($cases): array
    {
        $definitions = [
            'approved' => ['label' => 'Approved', 'color' => 'success'],
            'in_progress' => ['label' => 'In Progress', 'color' => 'primary'],
            'at_payer' => ['label' => 'At Payer', 'color' => 'warning'],
            'pending_provider' => ['label' => 'Pending w/ Provider', 'color' => 'info'],
            'documents_requested' => ['label' => 'Documents Requested', 'color' => 'danger'],
            'denied_closed' => ['label' => 'Denied / Closed', 'color' => 'secondary'],
        ];

        $counts = array_fill_keys(array_keys($definitions), 0);
        foreach ($cases as $case) {
            $bucket = $this->resolveStatusBucket($case->status?->dashboard_category, (string) ($case->status->name ?? ''));
            if (isset($counts[$bucket])) {
                $counts[$bucket]++;
            }
        }

        $total = max(1, $cases->count());
        $buckets = [];
        foreach ($definitions as $key => $meta) {
            $count = $counts[$key];
            $buckets[] = [
                'key' => $key,
                'label' => $meta['label'],
                'count' => $count,
                'percent' => round(($count / $total) * 100, 2),
                'color' => $meta['color'],
            ];
        }

        return $buckets;
    }

    public function resolveStatusBucket(?string $dashboardCategory, string $statusName = ''): string
    {
        if (str_contains(strtolower($statusName), 'documents requested')) {
            return 'documents_requested';
        }

        return match ($dashboardCategory) {
            'approved' => 'approved',
            'payer' => 'at_payer',
            'provider' => 'pending_provider',
            'closed' => 'denied_closed',
            'not_started', 'internal', 'on_hold', 'revalidation' => 'in_progress',
            default => 'in_progress',
        };
    }

    public function statusBucketBadgeClass(string $bucket): string
    {
        return match ($bucket) {
            'approved' => 'bg-label-success',
            'in_progress' => 'bg-label-primary',
            'at_payer' => 'bg-label-warning',
            'pending_provider' => 'bg-label-info',
            'documents_requested' => 'bg-label-danger',
            'denied_closed' => 'bg-label-secondary',
            default => 'bg-label-secondary',
        };
    }

    /**
     * @param  array{practice_id?: string|int|null, payer_id?: string|int|null, provider_id?: string|int|null, state?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     */
    /**
     * @return array{0: string, 1: string} Fill RGB, font RGB
     */
    public function excelStatusChipStyle(string $statusName, string $bucket = ''): array
    {
        $name = strtolower($statusName);

        if (str_contains($name, 'documents requested')) {
            return ['FCE7F3', '9D174D'];
        }
        if (str_contains($name, 'not started')) {
            return ['E5E7EB', '4B5563'];
        }
        if (str_contains($name, 'on hold')) {
            return ['FDE68A', '92400E'];
        }
        if (str_contains($name, 'ready to file')) {
            return ['D1FAE5', '065F46'];
        }
        if (str_contains($name, 'received') || str_contains($name, 'under review')) {
            return ['E0E7FF', '3730A3'];
        }
        if (str_contains($name, 'rejected') || str_contains($name, 'denied')) {
            return ['FEE2E2', '991B1B'];
        }
        if ($name === 'pending' || str_contains($name, 'at payer')) {
            return ['FEF3C7', '92400E'];
        }
        if (str_contains($name, 'approved')) {
            return ['DCFCE7', '166534'];
        }

        return match ($bucket) {
            'approved' => ['DCFCE7', '166534'],
            'at_payer' => ['FEF3C7', '92400E'],
            'pending_provider' => ['E0E7FF', '3730A3'],
            'documents_requested' => ['FCE7F3', '9D174D'],
            'denied_closed' => ['FEE2E2', '991B1B'],
            default => ['DBEAFE', '1D4ED8'],
        };
    }

    /**
     * @param  array{practice_id?: string|int|null, payer_id?: string|int|null, provider_id?: string|int|null, state?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     */
    protected function buildPracticeCredentialingSpreadsheet(array $filters = []): string
    {
        $payload = $this->practiceCredentialingExcelPayload($filters);
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Credentialing Report');

        $lastCol = 'L';
        $navy = $this->excelTitleFillColor();
        $zebra = $this->excelZebraFillColor();
        $bodyFont = '1F2937';
        $fontName = 'Carlito';

        $spreadsheet->getDefaultStyle()->getFont()->setName($fontName)->setSize(9);

        $sheet->mergeCells("A1:{$lastCol}2");
        $sheet->setCellValue('A1', 'Total Credentialing Report by Practice');
        $sheet->getStyle("A1:{$lastCol}2")->applyFromArray([
            'font' => ['name' => $fontName, 'bold' => true, 'size' => 18, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $navy]],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'horizontal' => Alignment::HORIZONTAL_LEFT,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->getRowDimension(2)->setRowHeight(22);

        $sheet->mergeCells("A3:{$lastCol}3");
        $sheet->setCellValue('A3', 'Credentialing activity report | Generated '.$payload['generated_at']);
        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['name' => $fontName, 'bold' => false, 'size' => 10, 'color' => ['rgb' => '6B7280']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $this->excelSubtitleFillColor()]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(20);
        $sheet->getRowDimension(4)->setRowHeight(8);

        $approvedCount = 0;
        $rejectedCount = 0;
        foreach ($payload['buckets'] as $bucket) {
            if ($bucket['key'] === 'approved') {
                $approvedCount = (int) $bucket['count'];
            }
            if ($bucket['key'] === 'denied_closed') {
                $rejectedCount = (int) $bucket['count'];
            }
        }
        $totalCases = (int) $payload['totals']['enrollments'];
        $openCount = max(0, $totalCases - $approvedCount - $rejectedCount);

        $kpis = [
            ['range' => 'A5:C6', 'cell' => 'A5', 'label' => 'Total Cases '.$totalCases],
            ['range' => 'D5:F6', 'cell' => 'D5', 'label' => 'Approved '.$approvedCount],
            ['range' => 'G5:I6', 'cell' => 'G5', 'label' => 'Open / In Progress '.$openCount],
            ['range' => 'J5:L6', 'cell' => 'J5', 'label' => 'Rejected '.$rejectedCount],
        ];
        $kpiBorder = [
            'borders' => [
                'outline' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E5E7EB'],
                ],
            ],
            'font' => ['name' => $fontName, 'bold' => true, 'size' => 12, 'color' => ['rgb' => $bodyFont]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'horizontal' => Alignment::HORIZONTAL_LEFT,
            ],
        ];
        foreach ($kpis as $kpi) {
            $sheet->mergeCells($kpi['range']);
            $sheet->setCellValue($kpi['cell'], $kpi['label']);
            $sheet->getStyle($kpi['range'])->applyFromArray($kpiBorder);
        }
        $sheet->getRowDimension(5)->setRowHeight(18);
        $sheet->getRowDimension(6)->setRowHeight(18);
        $sheet->getRowDimension(7)->setRowHeight(10);

        $headers = [
            'Practice', 'Client Code', 'Provider', 'NPI', 'Case Number',
            'Payer', 'State', 'Credentialing Status', 'Effective Date', 'Revalidation Due',
            'Latest Comment', 'Last Updated',
        ];
        $headerRow = 8;
        $sheet->fromArray($headers, null, "A{$headerRow}");
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font' => ['name' => $fontName, 'bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $navy]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(33.95);

        $row = $headerRow + 1;
        $dataIndex = 0;
        foreach ($payload['groups'] as $group) {
            foreach ($group['rows'] as $item) {
                $sheet->fromArray([[
                    $item['practice_name'] ?? '',
                    $item['client_code'] ?? '',
                    $item['provider_name'] ?? '',
                    $item['npi'] ?? '',
                    $item['case_number'] ?? '',
                    $item['payer_name'] ?? '',
                    $item['state'] ?? '',
                    $item['status_name'] ?? '',
                    $item['effective_date'] ?? '',
                    $item['revalidation_due'] ?? '',
                    $item['latest_comment'] ?? '',
                    $item['last_updated'] ?? '',
                ]], null, "A{$row}");

                $isZebra = $dataIndex % 2 === 1;
                $rowFill = $isZebra ? $zebra : 'FFFFFF';
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                    'font' => ['name' => $fontName, 'size' => 9, 'color' => ['rgb' => $bodyFont]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowFill]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                ]);

                [$chipFill, $chipFont] = $this->excelStatusChipStyle(
                    (string) ($item['status_name'] ?? ''),
                    (string) ($item['status_bucket'] ?? ''),
                );
                $sheet->getStyle("H{$row}")->applyFromArray([
                    'font' => ['name' => $fontName, 'bold' => true, 'size' => 8, 'color' => ['rgb' => $chipFont]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $chipFill]],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);

                $sheet->getRowDimension($row)->setRowHeight(33.95);
                $dataIndex++;
                $row++;
            }
        }

        if ($payload['groups'] === []) {
            $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
            $sheet->setCellValue("A{$row}", 'No enrollments match the current filters.');
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => ['name' => $fontName, 'size' => 10, 'color' => ['rgb' => '6B7280']],
            ]);
            $row++;
        }

        $lastDataRow = max($headerRow, $row - 1);
        $sheet->setAutoFilter("A{$headerRow}:{$lastCol}{$lastDataRow}");
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);

        $widths = [
            'A' => 24, 'B' => 12, 'C' => 20, 'D' => 14, 'E' => 18, 'F' => 28,
            'G' => 9, 'H' => 24, 'I' => 14, 'J' => 16, 'K' => 42, 'L' => 21,
        ];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $path = tempnam(sys_get_temp_dir(), 'pcrxlsx');
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $contents = file_get_contents($path) ?: '';
        @unlink($path);
        $spreadsheet->disconnectWorksheets();

        return $contents;
    }

    public function delayManagementBreakdown(): array
    {
        $query = CredentialingCase::active()
            ->with([
                'delayOwner', 'assignedAdmin', 'provider.user', 'payer', 'practice', 'status',
                'latestDelayOwnerHistory', 'latestStatusHistory',
            ]);
        $this->applyAdminScope($query);
        $cases = $query->get();

        $totalActive = $cases->count();

        $buildDimension = function (Collection $grouped, array $defaultLabels = []) use ($totalActive): array {
            $labels = collect($defaultLabels)
                ->merge($grouped->keys())
                ->unique()
                ->values();

            return $labels->map(function (string $label) use ($grouped, $totalActive) {
                /** @var Collection<int, CredentialingCase> $groupCases */
                $groupCases = $grouped->get($label, collect());
                $count = $groupCases->count();
                $percent = $totalActive > 0 ? round(($count / $totalActive) * 100, 1) : 0.0;
                $avgAging = $count > 0
                    ? (int) round($groupCases->avg(fn (CredentialingCase $c) => $c->aging_days))
                    : 0;
                $avgWaiting = $count > 0
                    ? (int) round($groupCases->avg(fn (CredentialingCase $c) => $c->current_waiting_days))
                    : 0;

                return [
                    'label' => $label,
                    'count' => $count,
                    'percent' => $percent,
                    'share_percent' => $percent,
                    'avg_aging_days' => $avgAging,
                    'avg_waiting_days' => $avgWaiting,
                ];
            })
                ->sortByDesc('count')
                ->values()
                ->all();
        };

        $byOwnerGrouped = $cases->groupBy(function (CredentialingCase $case): string {
            $raw = trim((string) ($case->delayOwner->name ?? ''));
            if ($raw === '' || strtolower($raw) === 'unassigned') {
                return 'Unassigned';
            }
            if ($raw === 'Provider') {
                return 'Provider/Practice';
            }
            if ($raw === 'Internal') {
                return 'MedCred Team';
            }

            return $raw;
        });

        $byTeamGrouped = $cases->groupBy(fn (CredentialingCase $case) => (string) ($case->assignedAdmin->name ?? 'Unassigned'));
        $byProviderGrouped = $cases->groupBy(fn (CredentialingCase $case) => (string) ($case->provider->user->name ?? 'Unknown'));
        $byPayerGrouped = $cases->groupBy(fn (CredentialingCase $case) => (string) ($case->payer->name ?? 'Unknown'));
        $byPracticeGrouped = $cases->groupBy(fn (CredentialingCase $case) => (string) ($case->practice->legal_name ?? 'Unknown'));

        return [
            'total_active' => $totalActive,
            'by_owner' => $buildDimension($byOwnerGrouped, ['Provider/Practice', 'MedCred Team', 'Payer', 'Unassigned']),
            'by_team' => $buildDimension($byTeamGrouped),
            'by_provider' => $buildDimension($byProviderGrouped),
            'by_payer' => $buildDimension($byPayerGrouped),
            'by_practice' => $buildDimension($byPracticeGrouped),
        ];
    }

    public function approvalDenialSummary(): array
    {
        $query = CredentialingCase::query()
            ->with(['payer', 'status', 'provider.specialty']);
        $this->applyAdminScope($query);
        $cases = $query->get();

        $isApproved = fn (CredentialingCase $c): bool => ($c->status?->dashboard_category === 'approved')
            || str_contains(strtolower((string) ($c->status?->name ?? '')), 'approved');

        $isPanelClosed = fn (CredentialingCase $c): bool => str_contains(strtolower((string) ($c->status?->name ?? '')), 'panel closed');

        $isDenied = function (CredentialingCase $c) use ($isPanelClosed): bool {
            if ($isPanelClosed($c)) {
                return false;
            }
            $name = strtolower((string) ($c->status?->name ?? ''));

            return str_contains($name, 'denied')
                || str_contains($name, 'rejected')
                || str_contains($name, 'not eligible');
        };

        $totalCases = $cases->count();
        $approvedCount = $cases->filter($isApproved)->count();
        $deniedCount = $cases->filter($isDenied)->count();
        $panelClosedCount = $cases->filter($isPanelClosed)->count();
        $approvalRatePercent = $totalCases > 0 ? round(($approvedCount / $totalCases) * 100, 1) : 0.0;

        $byPayer = $cases->groupBy(fn (CredentialingCase $c) => (string) ($c->payer->name ?? 'Unknown'))
            ->map(function (Collection $payerCases, string $payerName) use ($isApproved, $isDenied, $isPanelClosed) {
                $total = $payerCases->count();
                $approved = $payerCases->filter($isApproved)->count();
                $denied = $payerCases->filter($isDenied)->count();
                $panelClosed = $payerCases->filter($isPanelClosed)->count();
                $rate = $total > 0 ? round(($approved / $total) * 100, 1) : 0.0;

                $approvedWithDates = $payerCases->filter(fn (CredentialingCase $c) => $isApproved($c) && ($c->submission_date || $c->intake_date) && ($c->effective_date || $c->approval_date));
                $avgTurnaround = $approvedWithDates->isNotEmpty()
                    ? (int) round($approvedWithDates->avg(function (CredentialingCase $c) {
                        $start = $c->submission_date ?? $c->intake_date;
                        $end = $c->effective_date ?? $c->approval_date;

                        return $start && $end ? abs($start->diffInDays($end)) : 0;
                    }))
                    : null;

                return [
                    'payer' => $payerName,
                    'payer_name' => $payerName,
                    'total_cases' => $total,
                    'approved_count' => $approved,
                    'approval_rate_percent' => $rate,
                    'Approval_rate_percent' => $rate,
                    'denied_count' => $denied,
                    'panel_closed_count' => $panelClosed,
                    'avg_turnaround_days' => $avgTurnaround,
                ];
            })
            ->sortByDesc('total_cases')
            ->values()
            ->all();

        return [
            'total_cases' => $totalCases,
            'approved_count' => $approvedCount,
            'approval_rate_percent' => $approvalRatePercent,
            'Approval_rate_percent' => $approvalRatePercent,
            'denied_count' => $deniedCount,
            'panel_closed_count' => $panelClosedCount,
            'by_payer' => $byPayer,
            'payers' => $byPayer,
            'rows' => $byPayer,
        ];
    }

    protected function openApplicationsData(): array
    {
        $cases = CredentialingCase::active()
            ->with(['provider.user', 'payer', 'status', 'assignedAdmin', 'delayOwner', 'practice'])
            ->orderBy('case_number');
        $this->applyAdminScope($cases);
        $cases = $cases->get();

        return [
            'filename' => 'open_applications_'.now()->format('Ymd').'.csv',
            'headers' => [
                'Case Number', 'Provider', 'NPI', 'Payer', 'Practice', 'State', 'Status',
                'Assigned To', 'Delay Owner', 'Intake Date', 'Next Follow-up',
            ],
            'rows' => $cases->map(fn ($case) => [
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
            ])->values()->all(),
        ];
    }

    protected function exportOpenApplications(): StreamedResponse
    {
        $data = $this->openApplicationsData();

        return $this->streamCsv($data['filename'], $data['headers'], $data['rows']);
    }

    protected function documentComplianceData(): array
    {
        $cases = CredentialingCase::with([
            'provider.user', 'payer', 'documentItems',
        ])->orderBy('case_number');
        $this->applyAdminScope($cases);
        $cases = $cases->get();

        return [
            'filename' => 'document_compliance_'.now()->format('Ymd').'.csv',
            'headers' => [
                'Case Number', 'Provider', 'Payer', 'Required Docs', 'Received', 'Completion %',
            ],
            'rows' => $cases->map(function ($case) {
                $checklist = $case->checklist_completion;

                return [
                    $case->case_number,
                    $case->provider->user->name ?? '',
                    $case->payer->name ?? '',
                    $checklist['total'],
                    $checklist['received'],
                    $checklist['percent'],
                ];
            })->values()->all(),
        ];
    }

    protected function exportDocumentCompliance(): StreamedResponse
    {
        $data = $this->documentComplianceData();

        return $this->streamCsv($data['filename'], $data['headers'], $data['rows']);
    }

    protected function caseAgingData(): array
    {
        $cases = CredentialingCase::active()
            ->with(['provider.user', 'payer', 'status', 'latestDelayOwnerHistory', 'latestStatusHistory'])
            ->orderByDesc('intake_date');
        $this->applyAdminScope($cases);
        $cases = $cases->get();

        return [
            'filename' => 'case_aging_'.now()->format('Ymd').'.csv',
            'headers' => [
                'Case Number', 'Provider', 'Payer', 'Status', 'Intake Date', 'Submission Date', 'Aging Days', 'Current Waiting Days',
            ],
            'rows' => $cases->map(fn ($case) => [
                $case->case_number,
                $case->provider->user->name ?? '',
                $case->payer->name ?? '',
                $case->status->name ?? '',
                $case->intake_date?->format('Y-m-d') ?? '',
                $case->submission_date?->format('Y-m-d') ?? '',
                $case->aging_days,
                $case->current_waiting_days,
            ])->values()->all(),
        ];
    }

    protected function exportCaseAging(): StreamedResponse
    {
        $data = $this->caseAgingData();

        return $this->streamCsv($data['filename'], $data['headers'], $data['rows']);
    }

    protected function expiringDocumentsData(): array
    {
        $documents = Document::expiringSoon(30)
            ->with(['provider.user', 'documentType', 'credentialingCase'])
            ->orderBy('expiry_date');
        $admin = auth()->guard('admin')->user();
        if ($admin) {
            app(AdminScopeService::class)->scopeDocuments($documents, $admin);
        }
        $documents = $documents->get();

        return [
            'filename' => 'expiring_documents_'.now()->format('Ymd').'.csv',
            'headers' => [
                'Title', 'Type', 'Provider', 'Case Number', 'Expiry Date', 'State',
            ],
            'rows' => $documents->map(fn ($doc) => [
                $doc->title,
                $doc->documentType->name ?? '',
                $doc->provider->user->name ?? '',
                $doc->credentialingCase->case_number ?? '',
                $doc->expiry_date?->format('Y-m-d') ?? '',
                $doc->state ?? '',
            ])->values()->all(),
        ];
    }

    protected function exportExpiringDocuments(): StreamedResponse
    {
        $data = $this->expiringDocumentsData();

        return $this->streamCsv($data['filename'], $data['headers'], $data['rows']);
    }

    protected function productivityByExecutiveData(): array
    {
        $rows = app(ProductivityDashboardService::class)->executiveMetrics();

        return [
            'filename' => 'productivity_by_executive_'.now()->format('Ymd').'.csv',
            'headers' => [
                'Executive', 'Active Cases', 'Approved', 'Overdue', 'Open Tasks', 'Avg Turnaround Days',
            ],
            'rows' => $rows->map(fn ($r) => [
                $r['name'], $r['active_cases'], $r['approved_cases'], $r['overdue_cases'], $r['open_tasks'], $r['avg_turnaround_days'] ?? '',
            ])->values()->all(),
        ];
    }

    protected function exportProductivityByExecutive(): StreamedResponse
    {
        $data = $this->productivityByExecutiveData();

        return $this->streamCsv($data['filename'], $data['headers'], $data['rows']);
    }

    protected function turnaroundByPayerData(): array
    {
        $rows = app(ProductivityDashboardService::class)->payerTurnaround();

        return [
            'filename' => 'turnaround_by_payer_'.now()->format('Ymd').'.csv',
            'headers' => [
                'Payer', 'Approved Count', 'Avg Days',
            ],
            'rows' => $rows->map(fn ($r) => [$r['payer'], $r['approved_count'], $r['avg_days']])->values()->all(),
        ];
    }

    protected function exportTurnaroundByPayer(): StreamedResponse
    {
        $data = $this->turnaroundByPayerData();

        return $this->streamCsv($data['filename'], $data['headers'], $data['rows']);
    }

    protected function recredentialingUpcomingData(): array
    {
        $cases = app(ProductivityDashboardService::class)->recredentialingUpcoming();

        return [
            'filename' => 'recredentialing_upcoming_'.now()->format('Ymd').'.csv',
            'headers' => [
                'Case Number', 'Provider', 'Payer', 'Revalidation Due',
            ],
            'rows' => $cases->map(fn ($case) => [
                $case->case_number,
                $case->provider->user->name ?? '',
                $case->payer->name ?? '',
                $case->revalidation_due_date?->format('Y-m-d') ?? '',
            ])->values()->all(),
        ];
    }

    protected function exportRecredentialingUpcoming(): StreamedResponse
    {
        $data = $this->recredentialingUpcomingData();

        return $this->streamCsv($data['filename'], $data['headers'], $data['rows']);
    }

    protected function emailOperationsData(): array
    {
        $query = EmailMessage::query()
            ->with(['credentialingCase.practice', 'provider.user'])
            ->latest();

        $admin = auth()->guard('admin')->user();
        if ($admin) {
            $scope = app(AdminScopeService::class);
            $ids = $scope->assignedPracticeIds($admin);
            if ($ids !== null) {
                if ($ids === []) {
                    $query->whereRaw('0 = 1');
                } else {
                    $query->where(function ($q) use ($ids) {
                        $q->whereHas('credentialingCase', fn ($cq) => $cq->whereIn('practice_id', $ids))
                            ->orWhereHas('provider.practices', fn ($pq) => $pq->whereIn('practices.id', $ids));
                    });
                }
            }
        }

        $messages = $query->get();

        return [
            'filename' => 'email_operations_'.now()->format('Ymd').'.csv',
            'headers' => [
                'Subject', 'Direction', 'From', 'To', 'Status', 'Case Number', 'Provider', 'Unlinked', 'Pending Attachments', 'Date',
            ],
            'rows' => $messages->map(fn ($msg) => [
                $msg->subject ?? '',
                $msg->direction ?? '',
                $msg->from_address ?? '',
                $msg->to_address ?? '',
                $msg->status ?? '',
                $msg->credentialingCase->case_number ?? '',
                $msg->provider->user->name ?? '',
                $msg->is_unlinked ? 'Yes' : 'No',
                $msg->has_pending_attachments ? 'Yes' : 'No',
                ($msg->received_at ?? $msg->sent_at ?? $msg->created_at)?->format('Y-m-d H:i') ?? '',
            ])->values()->all(),
        ];
    }

    protected function exportEmailOperations(): StreamedResponse
    {
        $data = $this->emailOperationsData();

        return $this->streamCsv($data['filename'], $data['headers'], $data['rows']);
    }

    protected function delayOwnershipData(): array
    {
        $cases = CredentialingCase::active()
            ->with([
                'provider.user', 'payer', 'status', 'delayOwner', 'assignedAdmin', 'practice',
                'latestDelayOwnerHistory', 'latestStatusHistory',
            ])
            ->orderBy('case_number');
        $this->applyAdminScope($cases);
        $cases = $cases->get();

        return [
            'filename' => 'delay_ownership_'.now()->format('Ymd').'.csv',
            'headers' => [
                'Case Number', 'Practice', 'Provider', 'Payer', 'Status', 'Delay Owner', 'Assigned Executive', 'Aging Days', 'Current Waiting Days',
            ],
            'rows' => $cases->map(fn ($case) => [
                $case->case_number,
                $case->practice->legal_name ?? '',
                $case->provider->user->name ?? '',
                $case->payer->name ?? '',
                $case->status->name ?? '',
                $case->delayOwner->name ?? 'Unassigned',
                $case->assignedAdmin->name ?? '',
                $case->aging_days,
                $case->current_waiting_days,
            ])->values()->all(),
        ];
    }

    protected function exportDelayOwnership(): StreamedResponse
    {
        $data = $this->delayOwnershipData();

        return $this->streamCsv($data['filename'], $data['headers'], $data['rows']);
    }

    protected function approvalDenialTrendsData(): array
    {
        $query = CredentialingCase::query()
            ->with(['payer', 'status']);
        $this->applyAdminScope($query);
        $cases = $query->get();

        $rows = $cases->groupBy(fn (CredentialingCase $c) => ($c->payer->name ?? 'Unknown').'|'.($c->state ?: 'N/A'))
            ->map(function (Collection $group) {
                /** @var CredentialingCase $first */
                $first = $group->first();
                $payerName = $first->payer->name ?? 'Unknown';
                $state = $first->state ?: 'N/A';
                $total = $group->count();

                $approvedCases = $group->filter(fn (CredentialingCase $c) => ($c->status?->dashboard_category === 'approved')
                    || str_contains(strtolower((string) ($c->status?->name ?? '')), 'approved'));
                $approved = $approvedCases->count();

                $panelClosed = $group->filter(fn (CredentialingCase $c) => str_contains(strtolower((string) ($c->status?->name ?? '')), 'panel closed'))->count();

                $denied = $group->filter(function (CredentialingCase $c) {
                    $name = strtolower((string) ($c->status?->name ?? ''));
                    if (str_contains($name, 'panel closed')) {
                        return false;
                    }

                    return str_contains($name, 'denied')
                        || str_contains($name, 'rejected')
                        || str_contains($name, 'not eligible');
                })->count();

                $rate = $total > 0 ? round(($approved / $total) * 100, 1).'%' : '0%';

                $withDates = $approvedCases->filter(fn (CredentialingCase $c) => ($c->submission_date || $c->intake_date) && ($c->effective_date || $c->approval_date));
                $avgTurnaround = $withDates->isNotEmpty()
                    ? (string) round($withDates->avg(function (CredentialingCase $c) {
                        $start = $c->submission_date ?? $c->intake_date;
                        $end = $c->effective_date ?? $c->approval_date;

                        return $start && $end ? abs($start->diffInDays($end)) : 0;
                    }))
                    : '';

                return [
                    $payerName,
                    $state,
                    $total,
                    $approved,
                    $rate,
                    $denied,
                    $panelClosed,
                    $avgTurnaround,
                ];
            })
            ->values()
            ->all();

        return [
            'filename' => 'approval_denial_trends_'.now()->format('Ymd').'.csv',
            'headers' => [
                'Payer', 'State', 'Total Cases', 'Approved', 'Approval Rate %', 'Denied / Rejected', 'Panel Closed', 'Avg Turnaround Days',
            ],
            'rows' => $rows,
        ];
    }

    protected function exportApprovalDenialTrends(): StreamedResponse
    {
        $data = $this->approvalDenialTrendsData();

        return $this->streamCsv($data['filename'], $data['headers'], $data['rows']);
    }

    protected function panelClosedSummaryData(): array
    {
        $query = CredentialingCase::query()
            ->with(['practice', 'provider.user', 'provider.specialty', 'payer', 'status', 'latestActivity'])
            ->whereHas('status', function ($q) {
                $q->where('dashboard_category', 'closed')
                    ->orWhere('name', 'like', '%Panel Closed%')
                    ->orWhere('name', 'like', '%Denied%')
                    ->orWhere('name', 'like', '%Rejected%');
            })
            ->orderBy('case_number');

        $this->applyAdminScope($query);
        $cases = $query->get();

        return [
            'filename' => 'panel_closed_summary_'.now()->format('Ymd').'.csv',
            'headers' => [
                'Case Number', 'Practice', 'Provider', 'Specialty', 'Payer', 'State', 'Status', 'Intake Date', 'Last Updated', 'Notes',
            ],
            'rows' => $cases->map(fn (CredentialingCase $case) => [
                $case->case_number,
                $case->practice->legal_name ?? '',
                $case->provider->user->name ?? '',
                $case->provider->specialty->name ?? 'General',
                $case->payer->name ?? '',
                $case->state ?? '',
                $case->status->name ?? '',
                $case->intake_date?->format('Y-m-d') ?? '',
                $case->last_action_at?->format('Y-m-d') ?? '',
                (string) ($case->latestActivity?->summary ?? $case->notes ?? ''),
            ])->values()->all(),
        ];
    }

    protected function exportPanelClosedSummary(): StreamedResponse
    {
        $data = $this->panelClosedSummaryData();

        return $this->streamCsv($data['filename'], $data['headers'], $data['rows']);
    }

    protected function streamCsv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $this->sanitizeCsvRow($headers));

            foreach ($rows as $row) {
                $rowArray = is_array($row) ? $row : $row->toArray();
                fputcsv($handle, $this->sanitizeCsvRow($rowArray));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    protected function sanitizeCsvRow(array $row): array
    {
        return array_map(fn ($value) => $this->sanitizeCsvValue($value), $row);
    }

    protected function sanitizeCsvValue(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $firstChar = $value[0];
        if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true)) {
            if (in_array($firstChar, ['+', '-'], true) && is_numeric($value)) {
                return $value;
            }

            return "'".$value;
        }

        return $value;
    }

    protected function applyAdminScope($query): void
    {
        $admin = auth()->guard('admin')->user();
        if ($admin) {
            app(AdminScopeService::class)->scopeCredentialingCases($query, $admin);
        }
    }
}
