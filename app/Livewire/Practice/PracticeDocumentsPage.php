<?php

namespace App\Livewire\Practice;

use App\Models\CredentialingCase;
use App\Models\DocumentType;
use App\Services\DocumentService;
use App\Services\PracticeDashboardService;
use App\Support\UsStates;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
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

    public $state = '';

    public $uploadFile;

    public function saveUpload(): void
    {
        abort_unless(can_do('portal.documents.upload'), 403);

        $type = DocumentType::find($this->selectedDocumentTypeId ?: null);
        $stateRule = $type?->is_state_specific
            ? ['required', 'string', 'size:2', Rule::in(UsStates::codes())]
            : 'nullable|string|max:50';

        $this->validate([
            'selectedCaseId' => 'required|exists:credentialing_cases,id',
            'selectedDocumentTypeId' => 'nullable|exists:document_types,id',
            'title' => 'required|string|max:255',
            'state' => $stateRule,
            'uploadFile' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ]);

        $user = Auth::guard('web')->user();
        $practice = $user->practice;
        $case = CredentialingCase::where('practice_id', $practice->id)->findOrFail($this->selectedCaseId);

        $document = app(DocumentService::class)->upload([
            'title' => $this->title,
            'document_type_id' => $this->selectedDocumentTypeId ?: null,
            'provider_id' => $case->provider_id,
            'credentialing_case_id' => $case->id,
            'practice_id' => $practice->id,
            'state' => $this->state,
            'sync_credential' => (bool) $type?->is_state_specific,
        ], $this->uploadFile, null, $user->id);

        $case->addActivity('practice_upload', 'Practice uploaded: '.$document->title, null, null, null, $user->id);

        $this->reset(['selectedCaseId', 'selectedDocumentTypeId', 'title', 'state', 'uploadFile']);
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
            'states' => UsStates::all(),
            'selectedType' => DocumentType::find($this->selectedDocumentTypeId ?: null),
        ]);
    }
}
