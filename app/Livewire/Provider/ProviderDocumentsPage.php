<?php

namespace App\Livewire\Provider;

use App\Models\CredentialingCase;
use App\Models\DocumentType;
use App\Services\DocumentService;
use App\Services\ProviderDashboardService;
use App\Support\UsStates;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
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
        $provider = $user->providerDetails;
        $case = CredentialingCase::where('provider_id', $provider->id)->findOrFail($this->selectedCaseId);

        $document = app(DocumentService::class)->upload([
            'title' => $this->title,
            'document_type_id' => $this->selectedDocumentTypeId ?: null,
            'provider_id' => $provider->id,
            'credentialing_case_id' => $case->id,
            'practice_id' => $case->practice_id,
            'state' => $this->state,
            'sync_credential' => (bool) $type?->is_state_specific,
        ], $this->uploadFile, null, $user->id);

        $case->addActivity('provider_upload', 'Provider uploaded: '.$document->title, null, null, null, $user->id);

        $this->reset(['selectedCaseId', 'selectedDocumentTypeId', 'title', 'state', 'uploadFile']);
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
            'states' => UsStates::all(),
            'selectedType' => DocumentType::find($this->selectedDocumentTypeId ?: null),
        ]);
    }
}
