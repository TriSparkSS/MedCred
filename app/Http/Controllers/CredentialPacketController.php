<?php

namespace App\Http\Controllers;

use App\Models\CredentialingCase;
use App\Services\CredentialingPacketService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CredentialPacketController extends Controller
{
    public function download(CredentialingCase $case, CredentialingPacketService $service): BinaryFileResponse
    {
        $zipPath = $service->build($case);

        if (! $zipPath || ! file_exists($zipPath)) {
            abort(404, 'No documents available to include in the credentialing packet.');
        }

        return response()->download($zipPath, $case->case_number . '_credentialing_packet.zip')
            ->deleteFileAfterSend(true);
    }
}
