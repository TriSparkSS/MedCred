<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function download(string $type, ReportExportService $service): StreamedResponse
    {
        return $this->export($type, $service);
    }

    public function export(string $type, ReportExportService $service): StreamedResponse
    {
        if (! array_key_exists($type, ReportExportService::definitions())) {
            abort(404, 'Report not found.');
        }

        return $service->export($type);
    }

    public function practiceCredentialingXlsx(ReportExportService $service): StreamedResponse
    {
        return $service->streamPracticeCredentialingXlsx();
    }
}
