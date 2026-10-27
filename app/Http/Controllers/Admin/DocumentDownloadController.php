<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Practice;
use App\Services\AdminScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDownloadController
{
    public function download(Request $request, Document $document, AdminScopeService $scope): StreamedResponse
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin?->can('admin.documents.view'), 403);

        if ($admin && ! $scope->canAccessDocument($admin, $document->loadMissing(['credentialingCase', 'provider.practices']))) {
            abort(403);
        }

        $versionId = $request->integer('version');
        $version = $versionId
            ? $document->versions()->find($versionId)
            : ($document->versions()->where('is_current', true)->first() ?? $document->versions()->first());

        abort_unless($version?->file_path, 404, 'No file is attached to this document.');

        AuditLog::record('document.downloaded', $document, $admin?->id, [
            'version_id' => $version->id,
            'file_name' => $version->original_name,
        ]);

        return $this->stream(
            $version->file_path,
            $version->original_name ?: 'document',
            $version->mime_type,
            (bool) $version->is_encrypted,
        );
    }

    public function legacyPractice(Practice $practice, AdminScopeService $scope): StreamedResponse
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin?->can('admin.documents.view') || $admin?->can('admin.practices.view'), 403);

        if ($admin && ! $scope->canAccessPractice($admin, (int) $practice->id)) {
            abort(403);
        }

        abort_unless($practice->document_path, 404, 'No file is attached to this practice.');

        return $this->stream(
            $practice->document_path,
            $practice->document_original_name ?: 'practice-document',
        );
    }

    protected function stream(string $path, string $name, ?string $mime = null, bool $isEncrypted = false): StreamedResponse
    {
        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404, 'The document file could not be found.');

        $downloadName = str_replace(['"', "\r", "\n"], '', $name) ?: 'document';
        $headers = array_filter([
            'Content-Type' => $mime ?: null,
        ]);

        if ($isEncrypted) {
            $decrypted = Crypt::decryptString($disk->get($path));

            return response()->streamDownload(function () use ($decrypted): void {
                echo $decrypted;
            }, $downloadName, $headers);
        }

        return $disk->download($path, $downloadName, $headers);
    }
}
