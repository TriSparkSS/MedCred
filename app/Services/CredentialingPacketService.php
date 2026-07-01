<?php

namespace App\Services;

use App\Models\CredentialingCase;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class CredentialingPacketService
{
    public function build(CredentialingCase $case): ?string
    {
        $case->load([
            'provider.user',
            'practice',
            'payer',
            'documentItems.documentType',
            'documentItems.document.currentVersion',
            'documents.currentVersion',
        ]);

        $files = collect();

        foreach ($case->documents as $document) {
            $version = $document->current_version;
            if ($version && Storage::disk('public')->exists($version->file_path)) {
                $files->push([
                    'path' => Storage::disk('public')->path($version->file_path),
                    'name' => $this->sanitizeFilename($document->title . '_v' . $version->version_number . '_' . $version->original_name),
                ]);
            }
        }

        foreach ($case->documentItems as $item) {
            if ($item->document?->current_version) {
                $version = $item->document->current_version;
                if (Storage::disk('public')->exists($version->file_path)) {
                    $files->push([
                        'path' => Storage::disk('public')->path($version->file_path),
                        'name' => $this->sanitizeFilename(($item->documentType->name ?? 'doc') . '_' . $version->original_name),
                    ]);
                }
            }
        }

        $providerDocs = Document::where('provider_id', $case->provider_id)
            ->with('currentVersion')
            ->get();

        foreach ($providerDocs as $document) {
            $version = $document->current_version;
            if ($version && Storage::disk('public')->exists($version->file_path)) {
                $files->push([
                    'path' => Storage::disk('public')->path($version->file_path),
                    'name' => $this->sanitizeFilename('provider_' . $document->title . '_' . $version->original_name),
                ]);
            }
        }

        $files = $files->unique('name');

        if ($files->isEmpty()) {
            return null;
        }

        $zipFileName = storage_path('app/temp/' . $case->case_number . '_packet_' . now()->format('Ymd_His') . '.zip');
        if (! is_dir(dirname($zipFileName))) {
            mkdir(dirname($zipFileName), 0755, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        foreach ($files as $file) {
            $zip->addFile($file['path'], $file['name']);
        }

        $readme = $this->buildReadme($case);
        $zip->addFromString('00_Packet_Summary.txt', $readme);
        $zip->close();

        return $zipFileName;
    }

    protected function buildReadme(CredentialingCase $case): string
    {
        return implode("\n", [
            'Revantage Credentialing Packet',
            'Case Number: ' . $case->case_number,
            'Provider: ' . ($case->provider->user->name ?? 'N/A'),
            'NPI: ' . ($case->provider->npi ?? 'N/A'),
            'Practice: ' . ($case->practice->legal_name ?? 'N/A'),
            'Payer: ' . ($case->payer->name ?? 'N/A'),
            'State: ' . ($case->state ?? 'N/A'),
            'Generated: ' . now()->format('Y-m-d H:i:s'),
        ]);
    }

    protected function sanitizeFilename(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9._\-]/', '_', $name);
    }
}
