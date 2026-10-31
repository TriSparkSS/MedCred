<?php

namespace App\Livewire\Admin\Documents;

use App\Enums\AdminRole;
use App\Models\CredentialingCase;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\Task;
use App\Services\AdminScopeService;
use App\Services\DocumentService;
use App\Support\UsStates;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts::admin', ['title' => 'Documents'])]
class DocumentListPage extends Component
{
    use WithFileUploads, WithPagination;

    #[Url(as: 'view', history: true)]
    public string $viewMode = 'table';

    public $search = '';

    public $filterType = '';

    public $filterExpiry = '';

    public $filterState = '';

    public $filterProvider = '';

    public $filterPractice = '';

    public $filterPayer = '';

    public $showModal = false;

    public string $modalMode = 'upload';

    public $showVersionModal = false;

    #[Locked]
    public ?int $selectedDocumentId = null;

    public $uploadFile;

    public $versionFile;

    public $versionNotes = '';

    public $formData = [];

    public ?int $autoFilledPracticeId = null;

    public $rejectionReason = '';

    #[Locked]
    public ?int $rejectDocumentId = null;

    public function setViewMode(string $mode): void
    {
        $this->viewMode = in_array($mode, ['table', 'folders'], true) ? $mode : 'table';
    }

    protected function rules(): array
    {
        $type = DocumentType::find($this->formData['document_type_id'] ?? null);
        $stateSpecific = (bool) $type?->is_state_specific;

        return [
            'formData.title' => 'required|string|max:255',
            'formData.document_number' => 'nullable|string|max:100',
            'formData.document_type_id' => 'nullable|exists:document_types,id',
            'formData.provider_id' => $stateSpecific ? 'required|exists:provider_details,id' : 'nullable|exists:provider_details,id',
            'formData.practice_id' => 'nullable|exists:practices,id',
            'formData.credentialing_case_id' => 'nullable|exists:credentialing_cases,id',
            'formData.effective_date' => 'nullable|date',
            'formData.expiry_date' => 'nullable|date',
            'formData.state' => $stateSpecific
                ? ['required', 'string', 'size:2', Rule::in(UsStates::codes())]
                : 'nullable|string|max:50',
            'formData.visibility' => 'nullable|in:standard,restricted',
            'formData.allowed_roles' => 'nullable|array',
            'formData.allowed_roles.*' => 'string',
            'formData.encrypt_at_rest' => 'nullable|boolean',
            'formData.sync_credential' => 'boolean',
            'uploadFile' => ($this->modalMode === 'edit' ? 'nullable' : 'required').'|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ];
    }

    public function updated($propertyName): void
    {
        if (in_array($propertyName, ['search', 'filterType', 'filterExpiry', 'filterState', 'filterProvider', 'filterPractice', 'filterPayer'], true)) {
            $this->resetPage();
        }
    }

    public function updatedFormDataProviderId($value): void
    {
        if (! $value) {
            return;
        }

        $provider = ProviderDetails::with('practices')->find($value);
        if (! $provider) {
            return;
        }

        $admin = Auth::guard('admin')->user();
        $scope = app(AdminScopeService::class);
        $practices = $provider->practices;
        if ($admin) {
            $practices = $practices->filter(fn (Practice $p) => $scope->canAccessPractice($admin, (int) $p->id));
        }

        $practice = $practices->sortByDesc(fn (Practice $p) => (int) ($p->pivot->primary_flag ?? 0))->first();

        if (! $practice) {
            return;
        }

        $current = $this->formData['practice_id'] ?? null;
        if (! $current || (int) $current === (int) $this->autoFilledPracticeId) {
            $this->formData['practice_id'] = $practice->id;
            $this->autoFilledPracticeId = $practice->id;
        }
    }

    protected function findScopedDocument(int $id): Document
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin, 403);

        $scope = app(AdminScopeService::class);
        $query = Document::query();
        $scope->scopeDocuments($query, $admin);

        $document = $query->findOrFail($id);
        abort_unless($scope->canViewRestrictedDocument($admin, $document), 403);

        return $document;
    }

    public function openUploadModal(): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.documents.upload'), 403);

        $this->modalMode = 'upload';
        $this->selectedDocumentId = null;
        $this->resetForm();
        if ($this->filterProvider) {
            $this->formData['provider_id'] = $this->filterProvider;
            $this->updatedFormDataProviderId($this->filterProvider);
        }
        if ($this->filterPractice) {
            $this->formData['practice_id'] = $this->filterPractice;
        }
        $this->showModal = true;
    }

    public function openEditModal(int $documentId): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.documents.upload'), 403);

        $document = $this->findScopedDocument($documentId);

        $this->modalMode = 'edit';
        $this->selectedDocumentId = $document->id;
        $this->uploadFile = null;
        $this->formData = [
            'title' => $document->title,
            'document_number' => $document->document_number,
            'document_type_id' => $document->document_type_id,
            'provider_id' => $document->provider_id,
            'practice_id' => $document->practice_id,
            'credentialing_case_id' => $document->credentialing_case_id,
            'effective_date' => $document->effective_date?->format('Y-m-d'),
            'expiry_date' => $document->expiry_date?->format('Y-m-d'),
            'state' => $document->state,
            'visibility' => $document->visibility ?? 'standard',
            'allowed_roles' => $document->allowed_roles ?? [],
            'encrypt_at_rest' => false,
            'sync_credential' => (bool) $document->provider_id,
        ];
        $this->resetValidation();
        $this->showModal = true;
    }

    public function mount(): void
    {
        if (! in_array($this->viewMode, ['table', 'folders'], true)) {
            $this->viewMode = 'table';
        }

        if ($providerId = request()->query('provider')) {
            $this->filterProvider = $providerId;
            $this->formData['provider_id'] = $providerId;
        }

        if ($practiceId = request()->query('practice')) {
            $this->filterPractice = $practiceId;
            $this->formData['practice_id'] = $practiceId;
        }

        if (request()->query('filterExpiry') === 'expiring') {
            $this->filterExpiry = 'expiring';
        }

        if (request()->query('upload') && Auth::guard('admin')->user()?->can('admin.documents.upload')) {
            $this->openUploadModal();
        }
    }

    public function saveDocument(DocumentService $documentService): void
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin?->can('admin.documents.upload'), 403);

        $this->normalizeEmptyIds();
        $this->validate();

        $scope = app(AdminScopeService::class);
        if (! empty($this->formData['practice_id'])) {
            abort_unless($scope->canAccessPractice($admin, (int) $this->formData['practice_id']), 403);
        }
        if (! empty($this->formData['provider_id'])) {
            $provider = ProviderDetails::findOrFail($this->formData['provider_id']);
            abort_unless($scope->canAccessProvider($admin, $provider), 403);
        }
        if (! empty($this->formData['credentialing_case_id'])) {
            $case = CredentialingCase::findOrFail($this->formData['credentialing_case_id']);
            abort_unless($scope->canAccessCase($admin, $case), 403);
        }

        $adminId = $admin->id;

        if ($this->modalMode === 'edit' && $this->selectedDocumentId) {
            $existingDoc = $this->findScopedDocument((int) $this->selectedDocumentId);
            if (
                $existingDoc->visibility === 'restricted'
                && ! $scope->isUnrestricted($admin)
                && ! $admin->can('admin.settings.manage')
                && ! $admin->hasRole('super_admin')
            ) {
                $this->formData['visibility'] = 'restricted';
                $this->formData['allowed_roles'] = $existingDoc->allowed_roles ?? [];
            }

            $documentService->update(
                $existingDoc,
                $this->formData,
                $this->uploadFile,
                $adminId
            );
            flash()->success('Document updated successfully.');
        } else {
            $document = $documentService->upload($this->formData, $this->uploadFile, $adminId);

            if ($document->expiry_date && $document->expiry_date->lte(now()->addDays(30))) {
                $this->createExpiryTask($document);
            }

            flash()->success('Document uploaded successfully.');
        }

        $this->showModal = false;
        $this->modalMode = 'upload';
        $this->selectedDocumentId = null;
        $this->resetForm();
    }

    public function openVersionModal(int $documentId): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.documents.upload'), 403);

        $document = $this->findScopedDocument($documentId);
        $this->selectedDocumentId = $document->id;
        $this->versionFile = null;
        $this->versionNotes = '';
        $this->showVersionModal = true;
    }

    public function uploadNewVersion(): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.documents.upload'), 403);

        $this->validate([
            'versionFile' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'versionNotes' => 'nullable|string|max:500',
        ]);

        $adminId = Auth::guard('admin')->id();
        $document = $this->findScopedDocument((int) $this->selectedDocumentId);
        app(DocumentService::class)->uploadVersion($document, $this->versionFile, $adminId, $this->versionNotes);

        $this->showVersionModal = false;
        flash()->success('New version uploaded.');
    }

    public function verifyDocument(int $id, DocumentService $documentService): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.documents.verify'), 403);

        $documentService->verify($this->findScopedDocument($id), Auth::guard('admin')->id());
        flash()->success('Document verified.');
    }

    public function openRejectModal(int $id): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.documents.verify'), 403);

        $document = $this->findScopedDocument($id);
        $this->rejectDocumentId = $document->id;
        $this->rejectionReason = '';
    }

    public function rejectDocument(DocumentService $documentService): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.documents.verify'), 403);

        $this->validate(['rejectionReason' => 'required|string|max:1000']);
        $documentService->reject(
            $this->findScopedDocument((int) $this->rejectDocumentId),
            $this->rejectionReason,
            Auth::guard('admin')->id()
        );
        $this->rejectDocumentId = null;
        $this->rejectionReason = '';
        flash()->success('Document rejected.');
    }

    public function deleteDocument(int $id, DocumentService $documentService): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.documents.delete'), 403);

        $documentService->delete($this->findScopedDocument($id), Auth::guard('admin')->id());
        flash()->info('Document deleted.');
    }

    protected function createExpiryTask(Document $document): void
    {
        $automationKey = 'expiry:'.$document->id;

        if (Task::where('automation_key', $automationKey)->exists()) {
            return;
        }

        Task::create([
            'automation_key' => $automationKey,
            'task_type' => 'expiry',
            'provider_id' => $document->provider_id,
            'title' => 'Document expiring: '.$document->title,
            'description' => 'Expires on '.$document->expiry_date->format('m/d/Y'),
            'credentialing_case_id' => $document->credentialing_case_id,
            'assigned_admin_id' => Auth::guard('admin')->id(),
            'created_by_admin_id' => Auth::guard('admin')->id(),
            'due_date' => $document->expiry_date,
        ]);
    }

    private function normalizeEmptyIds(): void
    {
        foreach (['practice_id', 'provider_id', 'credentialing_case_id', 'document_type_id'] as $key) {
            if (($this->formData[$key] ?? '') === '') {
                $this->formData[$key] = null;
            }
        }
    }

    private function resetForm(): void
    {
        $this->formData = [
            'sync_credential' => true,
            'document_number' => '',
            'visibility' => 'standard',
            'allowed_roles' => [],
            'encrypt_at_rest' => false,
        ];
        $this->uploadFile = null;
        $this->autoFilledPracticeId = null;
        $this->resetValidation();
    }

    public function render()
    {
        $admin = Auth::guard('admin')->user();
        $scope = app(AdminScopeService::class);
        $query = Document::with([
            'documentType',
            'provider.user',
            'practice',
            'payer',
            'credentialingCase.payer',
            'versions' => fn ($q) => $this->viewMode === 'folders'
                ? $q->orderByDesc('version_number')
                : $q->where('is_current', true),
        ]);
        if ($admin) {
            $scope->scopeDocuments($query, $admin);
        }

        if ($this->search) {
            $search = '%'.$this->search.'%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                    ->orWhere('document_number', 'like', $search)
                    ->orWhereHas('provider.user', fn ($pq) => $pq->where('name', 'like', $search))
                    ->orWhereHas('practice', function ($pq) use ($search) {
                        $pq->where('legal_name', 'like', $search)
                            ->orWhere('dba_name', 'like', $search)
                            ->orWhere('client_code', 'like', $search);
                    })
                    ->orWhereHas('provider.practices', function ($pq) use ($search) {
                        $pq->where('legal_name', 'like', $search)
                            ->orWhere('dba_name', 'like', $search)
                            ->orWhere('client_code', 'like', $search);
                    })
                    ->orWhereHas('payer', fn ($pq) => $pq->where('name', 'like', $search))
                    ->orWhereHas('credentialingCase.payer', fn ($pq) => $pq->where('name', 'like', $search));
            });
        }

        if ($this->filterType) {
            $query->where('document_type_id', $this->filterType);
        }

        if ($this->filterExpiry === 'expiring') {
            $query->expiringSoon(30);
        } elseif ($this->filterExpiry === 'expired') {
            $query->expired();
        }

        if ($this->filterState) {
            $query->where('state', $this->filterState);
        }

        if ($this->filterProvider) {
            $query->where('provider_id', $this->filterProvider);
        }

        if ($this->filterPractice) {
            $query->where('practice_id', $this->filterPractice);
        }

        if ($this->filterPayer) {
            $query->where(function ($q) {
                $q->where('payer_id', $this->filterPayer)
                    ->orWhereHas('credentialingCase', fn ($cq) => $cq->where('payer_id', $this->filterPayer));
            });
        }

        $folderTree = [];
        if ($this->viewMode === 'folders') {
            $allScopedDocs = (clone $query)->orderBy('state')->latest()->get()
                ->filter(fn (Document $doc) => ! $admin || $scope->canViewRestrictedDocument($admin, $doc));

            foreach ($allScopedDocs as $doc) {
                $ownerName = $doc->provider?->user?->name
                    ? 'Provider: '.$doc->provider->user->name
                    : ($doc->practice?->legal_name
                        ? 'Practice: '.$doc->practice->legal_name
                        : 'General / Unassigned');
                $typeName = $doc->documentType?->name ?? 'Uncategorized';
                $folderTree[$ownerName][$typeName][] = $doc;
            }
        }

        $documents = $query->orderBy('state')->latest()->paginate(15);

        $statsBase = Document::query();
        $providerQuery = ProviderDetails::with('user');
        $practiceQuery = Practice::orderBy('legal_name');
        $caseQuery = CredentialingCase::with('provider.user')->latest();
        if ($admin) {
            $scope->scopeDocuments($statsBase, $admin);
            $scope->scopeProviders($providerQuery, $admin);
            $scope->scopePractices($practiceQuery, $admin);
            $scope->scopeCredentialingCases($caseQuery, $admin);
        }

        $stats = [
            'total' => (clone $statsBase)->count(),
            'expiring' => (clone $statsBase)->expiringSoon(30)->count(),
            'expired' => (clone $statsBase)->expired()->count(),
        ];

        return view('livewire.admin.documents.document-list-page', [
            'documents' => $documents,
            'folderTree' => $folderTree,
            'stats' => $stats,
            'documentTypes' => DocumentType::where('is_active', true)->orderBy('name')->get(),
            'providers' => $providerQuery->get(),
            'practices' => $practiceQuery->get(['id', 'legal_name', 'client_code']),
            'payers' => Payer::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'cases' => $caseQuery->limit(50)->get(),
            'states' => UsStates::all(),
            'availableRoles' => AdminRole::cases(),
            'selectedType' => DocumentType::find($this->formData['document_type_id'] ?? null),
            'canUpload' => (bool) $admin?->can('admin.documents.upload'),
        ]);
    }
}
