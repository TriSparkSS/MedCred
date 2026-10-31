<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function download(Request $request, string $type, ReportExportService $service, ?string $format = null): StreamedResponse
    {
        return $this->export($request, $type, $service, $format);
    }

    public function export(Request $request, string $type, ReportExportService $service, ?string $format = null): StreamedResponse
    {
        if (! array_key_exists($type, ReportExportService::definitions())) {
            abort(404, 'Report not found.');
        }

        $resolvedFormat = strtolower($format ?: (string) $request->query('format', 'csv'));
        if (! in_array($resolvedFormat, ['csv', 'xlsx', 'excel', 'pdf'], true)) {
            $resolvedFormat = 'csv';
        }

        $filters = $request->only(['practice_id', 'payer_id', 'provider_id', 'state', 'date_from', 'date_to']);

        return $service->export($type, $resolvedFormat, $filters);
    }

    public function practiceCredentialingXlsx(ReportExportService $service): StreamedResponse
    {
        return $service->streamPracticeCredentialingXlsx();
    }
}
