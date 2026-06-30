<?php

namespace App\Livewire\Practice;

use App\Models\CredentialingCase;
use App\Models\Document;
use App\Models\DocumentType;
use App\Services\PracticeDashboardService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts::practice', ['title' => 'Upload Center'])]
class PracticeDocumentsPage extends Component
{
    use WithFileUploads;

    public $selectedCaseId = '';

    public $selectedDocumentTypeId = '';

    public $title = '';

    public $uploadFile;

    public function saveUpload(): void
    {
        abort_unless(can_do('portal.documents.upload'), 403);

        $this->validate([
            'selectedCaseId' => 'required|exists:credentialing_cases,id',
            'selectedDocumentTypeId' => 'nullable|exists:document_types,id',
            'title' => 'required|string|max:255',
            'uploadFile' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ]);

        $user = Auth::guard('web')->user();
        $practice = $user->practice;
        $case = CredentialingCase::where('practice_id', $practice->id)->findOrFail($this->selectedCaseId);

        $document = Document::createWithFile([
            'title' => $this->title,
            'document_type_id' => $this->selectedDocumentTypeId ?: null,
            'provider_id' => $case->provider_id,
            'credentialing_case_id' => $case->id,
            'practice_id' => $practice->id,
        ], $this->uploadFile, null, $user->id);

        $case->syncChecklistFromDocument($document);
        $case->addActivity('practice_upload', 'Practice uploaded: ' . $document->title, null, null, null, $user->id);

        $this->reset(['selectedCaseId', 'selectedDocumentTypeId', 'title', 'uploadFile']);
        flash()->success('Document uploaded successfully.');
    }

    public function render(PracticeDashboardService $dashboard)
    {
        abort_unless(can_do('portal.documents.view'), 403);

        $practice = Auth::guard('web')->user()->practice;

        return view('livewire.practice.practice-documents-page', [
            'outstanding' => $dashboard->outstandingDocuments($practice),
            'cases' => CredentialingCase::where('practice_id', $practice->id)->active()->with(['payer', 'provider.user'])->get(),
            'documentTypes' => DocumentType::where('is_active', true)->orderBy('name')->get(),
            'myDocuments' => $practice->documents()->with('documentType')->latest()->limit(20)->get(),
            'canUpload' => can_do('portal.documents.upload'),
        ]);
    }
}
