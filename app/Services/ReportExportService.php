<?php

namespace App\Services;

use App\Models\CredentialingCase;
use App\Models\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
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
        ];
    }

    public function export(string $type): StreamedResponse
    {
        return match ($type) {
            'open_applications' => $this->exportOpenApplications(),
            'practice_credentialing_status' => $this->exportPracticeCredentialingStatus(),
            'document_compliance' => $this->exportDocumentCompliance(),
            'case_aging' => $this->exportCaseAging(),
            'expiring_documents' => $this->exportExpiringDocuments(),
            'productivity_by_executive' => $this->exportProductivityByExecutive(),
            'turnaround_by_payer' => $this->exportTurnaroundByPayer(),
            'recredentialing_upcoming' => $this->exportRecredentialingUpcoming(),
            default => abort(404, 'Report not found.'),
        };
    }

    /**
     * @param  array{practice_id?: string|int|null, payer_id?: string|int|null, provider_id?: string|int|null, state?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     * @return array{filename: string, headers: array<int, string>, rows: array<int, array<int, string|int|null>>}
     */
    public function rowsFor(string $type, array $filters = []): array
    {
        return match ($type) {
            'practice_credentialing_status' => $this->practiceCredentialingStatusData($filters),
            default => throw new \InvalidArgumentException("CSV email is not supported for report [{$type}]."),
        };
    }

    /**
     * @param  array{practice_id?: string|int|null, payer_id?: string|int|null, provider_id?: string|int|null, state?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     */
    public function csvContents(string $type, array $filters = []): string
    {
        $data = $this->rowsFor($type, $filters);
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $data['headers']);
        foreach ($data['rows'] as $row) {
            fputcsv($handle, $row);
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
        if ($type !== 'practice_credentialing_status') {
            throw new \InvalidArgumentException("Excel export is not supported for report [{$type}].");
        }

        return $this->buildPracticeCredentialingSpreadsheet($filters);
    }

    /**
     * @param  array{practice_id?: string|int|null, payer_id?: string|int|null, provider_id?: string|int|null, state?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     */
    public function streamPracticeCredentialingXlsx(array $filters = []): StreamedResponse
    {
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

    public function statusBucketFillColor(string $bucket): string
    {
        return match ($bucket) {
            'approved' => '28C76F',
            'in_progress' => '4C6FFF',
            'at_payer' => 'FF9F43',
            'pending_provider' => '9B7BFF',
            'documents_requested' => 'EA5455',
            'denied_closed' => 'A8AAAE',
            default => 'A8AAAE',
        };
    }

    protected function exportPracticeCredentialingStatus(): StreamedResponse
    {
        $data = $this->practiceCredentialingStatusData();

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
                'activities' => fn ($q) => $q->latest('id')->limit(1),
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
        $latest = $case->activities->first();
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
    protected function buildPracticeCredentialingSpreadsheet(array $filters = []): string
    {
        $payload = $this->practiceCredentialingExcelPayload($filters);
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Credentialing Report');

        $lastCol = 'I';
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', 'Total Credentialing Report by Practice');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '2F3A6D']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A2', 'Generated '.$payload['generated_at'].'  |  *All dates are in ET (Eastern Time)');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['size' => 10, 'color' => ['rgb' => '8A8797']],
        ]);

        $kpiLabels = [
            'Total Practices',
            'Total Providers',
            'Total Payer Enrollments',
        ];
        $kpiValues = [
            number_format($payload['totals']['practices']),
            number_format($payload['totals']['providers']),
            number_format($payload['totals']['enrollments']),
        ];
        foreach ($payload['buckets'] as $bucket) {
            $kpiLabels[] = $bucket['label'];
            $kpiValues[] = number_format($bucket['count']).' ('.number_format($bucket['percent'], 2).'%)';
        }

        foreach ($kpiLabels as $index => $label) {
            $col = chr(ord('A') + $index);
            $sheet->setCellValue($col.'4', $label);
            $sheet->setCellValue($col.'5', $kpiValues[$index] ?? '');
        }
        $sheet->getStyle("A4:{$lastCol}4")->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '8A8797']],
        ]);
        $sheet->getStyle("A5:{$lastCol}5")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '2F2B3D']],
        ]);

        $headers = [
            'Practice / Provider', 'NPI', 'Payer', 'State', 'Status',
            'Effective Date', 'Revalidation Due', 'Latest Comment', 'Last Updated',
        ];
        $headerRow = 7;
        $sheet->fromArray($headers, null, "A{$headerRow}");
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2F3A6D'],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(22);

        $row = $headerRow + 1;
        foreach ($payload['groups'] as $group) {
            $sheet->mergeCells("A{$row}:H{$row}");
            $sheet->setCellValue("A{$row}", $group['index'].'. '.$group['practice_name']);
            $sheet->setCellValue("I{$row}", 'Providers: '.$group['provider_count'].' | Payer Enrollments: '.$group['enrollment_count']);
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '2F2B3D']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F5F5F9'],
                ],
            ]);
            $row++;

            foreach ($group['rows'] as $item) {
                $sheet->fromArray([[
                    $item['provider_name'] ?: '—',
                    $item['npi'] ?: '—',
                    $item['payer_name'] ?: '—',
                    $item['state'] ?: '—',
                    $item['status_name'] ?: '—',
                    $item['effective_date'] ?: '—',
                    $item['revalidation_due'] ?: '—',
                    $item['latest_comment'] ?: '—',
                    $item['last_updated'] ?: '—',
                ]], null, "A{$row}");

                $bucket = (string) ($item['status_bucket'] ?? '');
                $fill = $this->statusBucketFillColor($bucket);
                $font = in_array($bucket, ['at_payer', 'pending_provider', 'denied_closed'], true) ? '2F2B3D' : 'FFFFFF';
                $sheet->getStyle("E{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => $font]],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $fill],
                    ],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getStyle("H{$row}")->getAlignment()->setWrapText(true);
                $row++;
            }
        }

        if ($payload['groups'] === []) {
            $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
            $sheet->setCellValue("A{$row}", 'No enrollments match the current filters.');
            $row++;
        }

        $lastDataRow = max($headerRow, $row - 1);
        $sheet->setAutoFilter("A{$headerRow}:{$lastCol}{$lastDataRow}");
        $sheet->freezePane('A8');
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastDataRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E7E7EF'],
                ],
            ],
        ]);

        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getColumnDimension('A')->setWidth(36);
        $sheet->getColumnDimension('H')->setWidth(42);

        $path = tempnam(sys_get_temp_dir(), 'pcrxlsx');
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $contents = file_get_contents($path) ?: '';
        @unlink($path);
        $spreadsheet->disconnectWorksheets();

        return $contents;
    }

    protected function exportOpenApplications(): StreamedResponse
    {
        $cases = CredentialingCase::active()
            ->with(['provider.user', 'payer', 'status', 'assignedAdmin', 'delayOwner', 'practice'])
            ->orderBy('case_number');
        $this->applyAdminScope($cases);
        $cases = $cases->get();

        return $this->streamCsv('open_applications_'.now()->format('Ymd').'.csv', [
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
        ])->orderBy('case_number');
        $this->applyAdminScope($cases);
        $cases = $cases->get();

        return $this->streamCsv('document_compliance_'.now()->format('Ymd').'.csv', [
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
            ->orderByDesc('intake_date');
        $this->applyAdminScope($cases);
        $cases = $cases->get();

        return $this->streamCsv('case_aging_'.now()->format('Ymd').'.csv', [
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
            ->orderBy('expiry_date');
        $admin = auth()->guard('admin')->user();
        if ($admin) {
            app(AdminScopeService::class)->scopeDocuments($documents, $admin);
        }
        $documents = $documents->get();

        return $this->streamCsv('expiring_documents_'.now()->format('Ymd').'.csv', [
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

        return $this->streamCsv('productivity_by_executive_'.now()->format('Ymd').'.csv', [
            'Executive', 'Active Cases', 'Approved', 'Overdue', 'Open Tasks', 'Avg Turnaround Days',
        ], $rows->map(fn ($r) => [
            $r['name'], $r['active_cases'], $r['approved_cases'], $r['overdue_cases'], $r['open_tasks'], $r['avg_turnaround_days'] ?? '',
        ]));
    }

    protected function exportTurnaroundByPayer(): StreamedResponse
    {
        $rows = app(ProductivityDashboardService::class)->payerTurnaround();

        return $this->streamCsv('turnaround_by_payer_'.now()->format('Ymd').'.csv', [
            'Payer', 'Approved Count', 'Avg Days',
        ], $rows->map(fn ($r) => [$r['payer'], $r['approved_count'], $r['avg_days']]));
    }

    protected function exportRecredentialingUpcoming(): StreamedResponse
    {
        $cases = app(ProductivityDashboardService::class)->recredentialingUpcoming();

        return $this->streamCsv('recredentialing_upcoming_'.now()->format('Ymd').'.csv', [
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

    protected function applyAdminScope($query): void
    {
        $admin = auth()->guard('admin')->user();
        if ($admin) {
            app(AdminScopeService::class)->scopeCredentialingCases($query, $admin);
        }
    }
}
