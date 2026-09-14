<?php

namespace App\Livewire\Admin\Documents;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Practice;
use App\Services\DocumentService;
use App\Support\UsStates;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class EntityDocumentsSection extends Component
{
    use WithFileUploads;

    public ?int $providerId = null;

    public ?int $practiceId = null;

    public bool $canUpload = false;

    public bool $canVerify = false;

    public bool $showModal = false;

    public bool $showVersionModal = false;

    public $uploadFile;

    public $versionFile;

    public string $versionNotes = '';

    public ?int $selectedDocumentId = null;

    public array $formData = [];

    public string $rejectionReason = '';

    public ?int $rejectDocumentId = null;

    public function mount(): void
    {
        abort_unless($this->providerId || $this->practiceId, 404);

        $admin = Auth::guard('admin')->user();
        $this->canUpload = $admin?->can('admin.documents.upload') ?? false;
        $this->canVerify = $admin?->can('admin.documents.verify') ?? false;
    }

    protected function rules(): array
    {
        $type = DocumentType::find($this->formData['document_type_id'] ?? null);
        $stateSpecific = (bool) $type?->is_state_specific && $this->providerId;

        return [
            'formData.title' => 'required|string|max:255',
            'formData.document_type_id' => 'nullable|exists:document_types,id',
            'formData.effective_date' => 'nullable|date',
            'formData.expiry_date' => 'nullable|date',
            'formData.state' => $stateSpecific
                ? ['required', 'string', 'size:2', Rule::in(UsStates::codes())]
                : 'nullable|string|max:50',
            'formData.sync_credential' => 'boolean',
            'uploadFile' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ];
    }

    public function openUploadModal(): void
    {
        abort_unless($this->canUpload, 403);

        $this->resetForm();
        $this->showModal = true;
    }

    public function saveDocument(DocumentService $documentService): void
    {
        abort_unless($this->canUpload, 403);

        $this->validate();

        $payload = [
            ...$this->formData,
            'provider_id' => $this->providerId,
            'practice_id' => $this->practiceId,
        ];

        if (! $this->providerId) {
            unset($payload['sync_credential'], $payload['state']);
        }

        $documentService->upload($payload, $this->uploadFile, Auth::guard('admin')->id());

        $this->showModal = false;
        $this->resetForm();
        flash()->success('Document uploaded successfully.');
    }

    public function openVersionModal(int $documentId): void
    {
        abort_unless($this->canUpload, 403);

        $this->documentForOwner($documentId);
        $this->selectedDocumentId = $documentId;
        $this->versionFile = null;
        $this->versionNotes = '';
        $this->showVersionModal = true;
    }

    public function uploadNewVersion(): void
    {
        abort_unless($this->canUpload, 403);

        $this->validate([
            'versionFile' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'versionNotes' => 'nullable|string|max:500',
        ]);

        $document = $this->documentForOwner((int) $this->selectedDocumentId);
        $document->addVersion($this->versionFile, Auth::guard('admin')->id(), $this->versionNotes);

        $this->showVersionModal = false;
        flash()->success('New version uploaded.');
    }

    public function verifyDocument(int $id, DocumentService $documentService): void
    {
        abort_unless($this->canVerify, 403);

        $documentService->verify($this->documentForOwner($id), Auth::guard('admin')->id());
        flash()->success('Document verified.');
    }

    public function openRejectModal(int $id): void
    {
        abort_unless($this->canVerify, 403);

        $this->documentForOwner($id);
        $this->rejectDocumentId = $id;
        $this->rejectionReason = '';
    }

    public function rejectDocument(DocumentService $documentService): void
    {
        abort_unless($this->canVerify, 403);

        $this->validate(['rejectionReason' => 'required|string|max:1000']);
        $documentService->reject(
            $this->documentForOwner((int) $this->rejectDocumentId),
            $this->rejectionReason,
            Auth::guard('admin')->id()
        );
        $this->rejectDocumentId = null;
        $this->rejectionReason = '';
        flash()->success('Document rejected.');
    }

    public function deleteDocument(int $id): void
    {
        abort_unless($this->canUpload, 403);

        $this->documentForOwner($id)->delete();
        flash()->info('Document deleted.');
    }

    private function documentForOwner(int $id): Document
    {
        $query = Document::query();

        if ($this->providerId) {
            $query->where('provider_id', $this->providerId);
        } else {
            $query->where('practice_id', $this->practiceId);
        }

        return $query->findOrFail($id);
    }

    private function resetForm(): void
    {
        $this->formData = [
            'provider_id' => $this->providerId,
            'practice_id' => $this->practiceId,
            'sync_credential' => (bool) $this->providerId,
        ];
        $this->uploadFile = null;
        $this->resetValidation();
    }

    public function render()
    {
        $query = Document::with(['documentType', 'versions' => fn ($q) => $q->where('is_current', true)]);

        if ($this->providerId) {
            $query->where('provider_id', $this->providerId);
        } else {
            $query->where('practice_id', $this->practiceId);
        }

        $typesQuery = DocumentType::where('is_active', true)->orderBy('name');
        if (! $this->providerId) {
            $typesQuery->where('is_state_specific', false);
        }

        $legacy = null;
        if ($this->practiceId) {
            $practice = Practice::find($this->practiceId);
            if ($practice?->document_path) {
                $legacy = [
                    'name' => $practice->document_original_name ?: 'Practice document',
                    'path' => $practice->document_path,
                ];
            }
        }

        $hubParams = $this->providerId
            ? ['provider' => $this->providerId]
            : ['practice' => $this->practiceId];

        return view('livewire.admin.documents.partials.entity-documents-section', [
            'documents' => $query->latest()->get(),
            'documentTypes' => $typesQuery->get(),
            'states' => UsStates::all(),
            'selectedType' => DocumentType::find($this->formData['document_type_id'] ?? null),
            'legacy' => $legacy,
            'hubUrl' => route('admin.documents', $hubParams),
            'isProvider' => (bool) $this->providerId,
        ]);
    }
}
