<?php

namespace App\Http\Controllers;

use App\Services\ReportExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function download(string $type, ReportExportService $service): StreamedResponse
    {
        if (! array_key_exists($type, ReportExportService::definitions())) {
            abort(404, 'Report not found.');
        }

        return $service->export($type);
    }
}
