<?php

namespace App\Http\Controllers\Admin;

use App\Models\Document;
use App\Models\Practice;
use App\Services\AdminScopeService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDownloadController
{
    public function download(Document $document, AdminScopeService $scope): StreamedResponse
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin?->can('admin.documents.view'), 403);

        if ($admin && ! $scope->canAccessDocument($admin, $document->loadMissing(['credentialingCase', 'provider.practices']))) {
            abort(403);
        }

        $version = $document->versions()->where('is_current', true)->first()
            ?? $document->versions()->first();

        abort_unless($version?->file_path, 404, 'No file is attached to this document.');

        return $this->stream($version->file_path, $version->original_name ?: 'document', $version->mime_type);
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

    protected function stream(string $path, string $name, ?string $mime = null): StreamedResponse
    {
        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404, 'The document file could not be found.');

        $downloadName = str_replace(['"', "\r", "\n"], '', $name) ?: 'document';

        return $disk->download($path, $downloadName, array_filter([
            'Content-Type' => $mime ?: null,
        ]));
    }
}
