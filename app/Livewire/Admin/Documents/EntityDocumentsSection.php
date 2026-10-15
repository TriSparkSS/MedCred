<?php

namespace App\Livewire\Admin\Documents;

use App\Models\CredentialingCase;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Services\AdminScopeService;
use App\Services\DocumentService;
use App\Support\UsStates;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class EntityDocumentsSection extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?int $providerId = null;

    #[Locked]
    public ?int $practiceId = null;

    #[Locked]
    public ?int $caseId = null;

    #[Locked]
    public ?string $lockedTypeName = null;

    public ?string $heading = null;

    public bool $showProfileLink = true;

    #[Locked]
    public bool $canUpload = false;

    #[Locked]
    public bool $canVerify = false;

    public bool $showModal = false;

    public string $modalMode = 'upload';

    public bool $showVersionModal = false;

    public $uploadFile;

    public $versionFile;

    public string $versionNotes = '';

    #[Locked]
    public ?int $selectedDocumentId = null;

    public array $formData = [];

    public string $rejectionReason = '';

    #[Locked]
    public ?int $rejectDocumentId = null;

    public function mount(): void
    {
        abort_unless($this->providerId || $this->practiceId || $this->caseId, 404);

        if ($this->caseId) {
            $case = CredentialingCase::findOrFail($this->caseId);
            if (! $this->practiceId && $case->practice_id) {
                $this->practiceId = (int) $case->practice_id;
            }
            if (! $this->providerId && $case->provider_id) {
                $this->providerId = (int) $case->provider_id;
            }
        }

        $this->authorizeEntityAccess();

        $admin = Auth::guard('admin')->user();
        $this->canUpload = $admin?->can('admin.documents.upload') ?? false;
        $this->canVerify = $admin?->can('admin.documents.verify') ?? false;
    }

    protected function authorizeEntityAccess(): void
    {
        $admin = Auth::guard('admin')->user();
        if (! $admin) {
            return;
        }

        $scope = app(AdminScopeService::class);

        if ($this->caseId) {
            $case = CredentialingCase::findOrFail($this->caseId);
            if (! $scope->canAccessCase($admin, $case)) {
                abort(403);
            }

            return;
        }

        if ($this->practiceId && ! $scope->canAccessPractice($admin, (int) $this->practiceId)) {
            abort(403);
        }

        if ($this->providerId) {
            $provider = ProviderDetails::findOrFail($this->providerId);
            if (! $scope->canAccessProvider($admin, $provider)) {
                abort(403);
            }
        }
    }

    protected function rules(): array
    {
        $type = DocumentType::find($this->formData['document_type_id'] ?? null);
        $stateSpecific = (bool) $type?->is_state_specific && $this->providerId && ! $this->caseId;

        return [
            'formData.title' => 'required|string|max:255',
            'formData.document_number' => 'nullable|string|max:100',
            'formData.document_type_id' => 'nullable|exists:document_types,id',
            'formData.effective_date' => 'nullable|date',
            'formData.expiry_date' => 'nullable|date',
            'formData.state' => $stateSpecific
                ? ['required', 'string', 'size:2', Rule::in(UsStates::codes())]
                : 'nullable|string|max:50',
            'formData.sync_credential' => 'nullable|boolean',
            'formData.link_to_practice' => 'nullable|boolean',
            'formData.link_to_provider' => 'nullable|boolean',
            'uploadFile' => $this->modalMode === 'edit'
                ? 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240'
                : 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ];
    }

    public function openUploadModal(): void
    {
        $this->canUpload = (bool) Auth::guard('admin')->user()?->can('admin.documents.upload');
        abort_unless($this->canUpload, 403);
        $this->authorizeEntityAccess();

        $this->modalMode = 'upload';
        $this->selectedDocumentId = null;
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal(int $documentId): void
    {
        $this->canUpload = (bool) Auth::guard('admin')->user()?->can('admin.documents.upload');
        abort_unless($this->canUpload, 403);
        $this->authorizeEntityAccess();

        $document = $this->documentForOwner($documentId);

        $this->modalMode = 'edit';
        $this->selectedDocumentId = $document->id;
        $this->uploadFile = null;
        $this->formData = [
            'provider_id' => $document->provider_id,
            'practice_id' => $document->practice_id,
            'sync_credential' => (bool) $this->providerId && ! $this->caseId,
            'link_to_practice' => (bool) $document->practice_id,
            'link_to_provider' => (bool) $document->provider_id,
            'document_type_id' => $document->document_type_id,
            'title' => $document->title ?? '',
            'document_number' => $document->document_number ?? '',
            'effective_date' => $document->effective_date?->format('Y-m-d') ?? '',
            'expiry_date' => $document->expiry_date?->format('Y-m-d') ?? '',
            'state' => $document->state,
        ];
        $this->resetValidation();
        $this->showModal = true;
    }

    public function saveDocument(DocumentService $documentService): void
    {
        $this->canUpload = (bool) Auth::guard('admin')->user()?->can('admin.documents.upload');
        abort_unless($this->canUpload, 403);
        $this->authorizeEntityAccess();

        if (! $this->uploadFile) {
            $this->uploadFile = null;
        }

        $this->normalizeEmptyFormValues();
        $this->validate();

        $payload = $this->formData;

        if ($this->caseId) {
            $case = CredentialingCase::findOrFail($this->caseId);
            $payload['credentialing_case_id'] = $case->id;
            $payload['payer_id'] = $case->payer_id;
            $payload['practice_id'] = ! empty($this->formData['link_to_practice']) ? $case->practice_id : null;
            $payload['provider_id'] = ! empty($this->formData['link_to_provider']) ? $case->provider_id : null;
            unset($payload['link_to_practice'], $payload['link_to_provider'], $payload['sync_credential']);
        } else {
            unset($payload['link_to_practice'], $payload['link_to_provider']);

            if ($this->providerId) {
                $payload['provider_id'] = $this->providerId;
            } else {
                unset($payload['provider_id'], $payload['sync_credential'], $payload['state']);
            }

            if ($this->practiceId) {
                $payload['practice_id'] = $this->practiceId;
            } else {
                unset($payload['practice_id']);
            }
        }

        if ($this->modalMode === 'edit' && $this->selectedDocumentId) {
            $document = $documentService->update(
                $this->documentForOwner((int) $this->selectedDocumentId),
                $payload,
                $this->uploadFile,
                Auth::guard('admin')->id()
            );
            flash()->success('Document updated successfully.');
        } else {
            $document = $documentService->upload($payload, $this->uploadFile, Auth::guard('admin')->id());
            flash()->success('Document uploaded successfully.');
        }

        $this->syncPracticeLicenseFromDocument($document);

        $this->showModal = false;
        $this->modalMode = 'upload';
        $this->selectedDocumentId = null;
        $this->resetForm();
    }

    public function openVersionModal(int $documentId): void
    {
        $this->canUpload = (bool) Auth::guard('admin')->user()?->can('admin.documents.upload');
        abort_unless($this->canUpload, 403);
        $this->authorizeEntityAccess();

        $this->documentForOwner($documentId);
        $this->selectedDocumentId = $documentId;
        $this->versionFile = null;
        $this->versionNotes = '';
        $this->showVersionModal = true;
    }

    public function uploadNewVersion(): void
    {
        $this->canUpload = (bool) Auth::guard('admin')->user()?->can('admin.documents.upload');
        abort_unless($this->canUpload, 403);
        $this->authorizeEntityAccess();

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
        $this->canVerify = (bool) Auth::guard('admin')->user()?->can('admin.documents.verify');
        abort_unless($this->canVerify, 403);
        $this->authorizeEntityAccess();

        $documentService->verify($this->documentForOwner($id), Auth::guard('admin')->id());
        flash()->success('Document verified.');
    }

    public function openRejectModal(int $id): void
    {
        $this->canVerify = (bool) Auth::guard('admin')->user()?->can('admin.documents.verify');
        abort_unless($this->canVerify, 403);
        $this->authorizeEntityAccess();

        $this->documentForOwner($id);
        $this->rejectDocumentId = $id;
        $this->rejectionReason = '';
    }

    public function rejectDocument(DocumentService $documentService): void
    {
        $this->canVerify = (bool) Auth::guard('admin')->user()?->can('admin.documents.verify');
        abort_unless($this->canVerify, 403);
        $this->authorizeEntityAccess();

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
        $admin = Auth::guard('admin')->user();
        abort_unless((bool) $admin?->can('admin.documents.delete') || (bool) $admin?->can('admin.documents.upload'), 403);
        $this->authorizeEntityAccess();

        $this->documentForOwner($id)->delete();
        flash()->info('Document deleted.');
    }

    public function deleteLegacyDocument(): void
    {
        $admin = Auth::guard('admin')->user();
        abort_unless((bool) $admin?->can('admin.documents.delete') || (bool) $admin?->can('admin.documents.upload'), 403);
        $this->authorizeEntityAccess();

        if (! $this->practiceId) {
            return;
        }

        $practice = Practice::findOrFail($this->practiceId);
        if ($practice->document_path) {
            Storage::disk('public')->delete($practice->document_path);
            $practice->update([
                'document_path' => null,
                'document_original_name' => null,
            ]);
            flash()->info('Legacy practice document deleted.');
        }
    }

    private function syncPracticeLicenseFromDocument(Document $document): void
    {
        if (! $this->practiceId || $document->documentType?->name !== Practice::LICENSE_DOCUMENT_TYPE) {
            return;
        }

        Practice::whereKey($this->practiceId)->update([
            'license_name' => $document->title,
            'license_number' => $document->document_number,
            'license_effective_date' => $document->effective_date,
            'license_expiry_date' => $document->expiry_date,
        ]);
    }

    private function documentForOwner(int $id): Document
    {
        $query = Document::query()->with('documentType');

        if ($this->caseId) {
            $query->where('credentialing_case_id', $this->caseId);
        } elseif ($this->providerId) {
            $query->where('provider_id', $this->providerId);
        } else {
            $query->where('practice_id', $this->practiceId);
        }

        return $query->findOrFail($id);
    }

    private function normalizeEmptyFormValues(): void
    {
        foreach (['practice_id', 'provider_id', 'document_type_id'] as $key) {
            if (($this->formData[$key] ?? '') === '') {
                $this->formData[$key] = null;
            }
        }

        foreach (['effective_date', 'expiry_date'] as $key) {
            if (($this->formData[$key] ?? '') === '') {
                $this->formData[$key] = null;
            }
        }
    }

    private function lockedType(): ?DocumentType
    {
        if (! filled($this->lockedTypeName)) {
            return null;
        }

        return DocumentType::query()
            ->where('name', $this->lockedTypeName)
            ->first();
    }

    private function resetForm(): void
    {
        $this->formData = [
            'provider_id' => $this->providerId,
            'practice_id' => $this->practiceId,
            'sync_credential' => (bool) $this->providerId && ! $this->caseId,
            'link_to_practice' => (bool) $this->practiceId,
            'link_to_provider' => (bool) $this->providerId,
            'document_type_id' => $this->lockedType()?->id,
            'title' => $this->lockedTypeName ?: '',
            'document_number' => '',
            'effective_date' => '',
            'expiry_date' => '',
            'state' => null,
        ];
        $this->uploadFile = null;
        $this->resetValidation();
    }

    public function render()
    {
        $this->authorizeEntityAccess();

        $query = Document::with(['documentType', 'versions' => fn ($q) => $q->where('is_current', true)]);

        if ($this->caseId) {
            $query->where('credentialing_case_id', $this->caseId);
        } elseif ($this->providerId) {
            $query->where('provider_id', $this->providerId);
        } else {
            $query->where('practice_id', $this->practiceId);
        }

        $lockedType = $this->lockedType();
        if ($lockedType) {
            $query->where('document_type_id', $lockedType->id);
        }

        $typesQuery = DocumentType::where('is_active', true)->orderBy('name');
        if (! $this->providerId && ! $this->caseId) {
            $typesQuery->where('is_state_specific', false);
        }

        $legacy = null;
        if ($this->practiceId && ! $this->caseId && ! $lockedType) {
            $practice = Practice::find($this->practiceId);
            if ($practice?->document_path) {
                $legacy = [
                    'name' => $practice->license_name ?: ($practice->document_original_name ?: 'Practice document'),
                    'number' => $practice->license_number,
                    'effective' => $practice->license_effective_date?->format('m/d/Y'),
                    'expiration' => $practice->license_expiry_date?->format('m/d/Y'),
                    'path' => $practice->document_path,
                ];
            }
        }

        $hubParams = $this->caseId
            ? ['case' => $this->caseId]
            : ($this->providerId ? ['provider' => $this->providerId] : ['practice' => $this->practiceId]);

        $heading = $this->heading
            ?: ($this->caseId ? 'Additional Application Documents' : ($this->providerId ? 'Provider Documents' : 'License & Documents'));

        return view('livewire.admin.documents.partials.entity-documents-section', [
            'documents' => $query->latest()->get(),
            'documentTypes' => $typesQuery->get(),
            'states' => UsStates::all(),
            'selectedType' => DocumentType::find($this->formData['document_type_id'] ?? $lockedType?->id),
            'legacy' => $legacy,
            'hubUrl' => route('admin.documents', $hubParams),
            'isProvider' => (bool) $this->providerId && ! $this->caseId,
            'isCase' => (bool) $this->caseId,
            'heading' => $heading,
            'typeIsLocked' => (bool) $lockedType,
            'viewAllTabUrl' => $this->showProfileLink && ! $this->caseId
                ? ($this->providerId
                    ? route('admin.providers.show', ['provider' => $this->providerId, 'tab' => 'documents'])
                    : route('admin.practices.show', ['practice' => $this->practiceId, 'tab' => 'documents']))
                : null,
        ]);
    }
}
