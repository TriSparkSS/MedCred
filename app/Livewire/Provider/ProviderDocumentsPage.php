<?php

namespace App\Livewire\Provider;

use App\Models\CredentialingCase;
use App\Models\Document;
use App\Models\DocumentType;
use App\Services\ProviderDashboardService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts::provider', ['title' => 'Upload Center'])]
class ProviderDocumentsPage extends Component
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
        $provider = $user->providerDetails;
        $case = CredentialingCase::where('provider_id', $provider->id)->findOrFail($this->selectedCaseId);

        $document = Document::createWithFile([
            'title' => $this->title,
            'document_type_id' => $this->selectedDocumentTypeId ?: null,
            'provider_id' => $provider->id,
            'credentialing_case_id' => $case->id,
            'practice_id' => $case->practice_id,
        ], $this->uploadFile, null, $user->id);

        $case->syncChecklistFromDocument($document);
        $case->addActivity('provider_upload', 'Provider uploaded: ' . $document->title, null, null, null, $user->id);

        $this->reset(['selectedCaseId', 'selectedDocumentTypeId', 'title', 'uploadFile']);
        flash()->success('Document uploaded successfully.');
    }

    public function render(ProviderDashboardService $dashboard)
    {
        abort_unless(can_do('portal.documents.view'), 403);

        $provider = Auth::guard('web')->user()->providerDetails;

        return view('livewire.provider.provider-documents-page', [
            'outstanding' => $dashboard->outstandingDocuments($provider),
            'cases' => CredentialingCase::where('provider_id', $provider->id)->active()->with('payer')->get(),
            'documentTypes' => DocumentType::where('is_active', true)->orderBy('name')->get(),
            'myDocuments' => $provider->documents()->with('documentType')->latest()->limit(20)->get(),
            'canUpload' => can_do('portal.documents.upload'),
        ]);
    }
}
