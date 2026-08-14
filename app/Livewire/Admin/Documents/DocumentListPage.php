<?php

namespace App\Livewire\Admin\Documents;

use App\Models\Admin;
use App\Models\CredentialingCase;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\Task;
use App\Services\DocumentService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts::admin', ['title' => 'Documents'])]
class DocumentListPage extends Component
{
    use WithFileUploads, WithPagination;

    public $search = '';

    public $filterType = '';

    public $filterExpiry = '';

    public $showModal = false;

    public $showVersionModal = false;

    public $selectedDocumentId = null;

    public $uploadFile;

    public $versionFile;

    public $versionNotes = '';

    public $formData = [];

    public $rejectionReason = '';

    public $rejectDocumentId = null;

    protected function rules(): array
    {
        return [
            'formData.title' => 'required|string|max:255',
            'formData.document_type_id' => 'nullable|exists:document_types,id',
            'formData.provider_id' => 'nullable|exists:provider_details,id',
            'formData.practice_id' => 'nullable|exists:practices,id',
            'formData.credentialing_case_id' => 'nullable|exists:credentialing_cases,id',
            'formData.effective_date' => 'nullable|date',
            'formData.expiry_date' => 'nullable|date',
            'formData.state' => 'nullable|string|max:50',
            'uploadFile' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ];
    }

    public function updated($propertyName): void
    {
        if (in_array($propertyName, ['search', 'filterType', 'filterExpiry'])) {
            $this->resetPage();
        }
    }

    public function openUploadModal(): void
    {
        $this->resetForm();
        if ($providerId = request()->query('provider')) {
            $this->formData['provider_id'] = $providerId;
        }
        $this->showModal = true;
    }

    public function mount(): void
    {
        $this->syncExpiringDocumentTasks();

        if ($providerId = request()->query('provider')) {
            $this->formData['provider_id'] = $providerId;
        }

        if (request()->query('filterExpiry') === 'expiring') {
            $this->filterExpiry = 'expiring';
        }

        if (request()->query('upload')) {
            $this->openUploadModal();
        }
    }

    protected function syncExpiringDocumentTasks(): void
    {
        $adminId = Auth::guard('admin')->id();

        Document::expiringSoon(30)->with('documentType')->each(function (Document $document) use ($adminId) {
            $this->createExpiryTask($document);
        });
    }

    public function saveDocument(DocumentService $documentService): void
    {
        $this->validate();

        $adminId = Auth::guard('admin')->id();
        $document = $documentService->upload($this->formData, $this->uploadFile, $adminId);

        if ($document->credentialing_case_id) {
            $case = CredentialingCase::find($document->credentialing_case_id);
            $case?->syncChecklistFromDocument($document);
        }

        if ($document->expiry_date && $document->expiry_date->lte(now()->addDays(30))) {
            $this->createExpiryTask($document);
        }

        $this->showModal = false;
        $this->resetForm();
        flash()->success('Document uploaded successfully.');
    }

    public function openVersionModal(int $documentId): void
    {
        $this->selectedDocumentId = $documentId;
        $this->versionFile = null;
        $this->versionNotes = '';
        $this->showVersionModal = true;
    }

    public function uploadNewVersion(): void
    {
        $this->validate([
            'versionFile' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'versionNotes' => 'nullable|string|max:500',
        ]);

        $document = Document::findOrFail($this->selectedDocumentId);
        $document->addVersion($this->versionFile, Auth::guard('admin')->id(), $this->versionNotes);

        $this->showVersionModal = false;
        flash()->success('New version uploaded.');
    }

    public function verifyDocument(int $id, DocumentService $documentService): void
    {
        $documentService->verify(Document::findOrFail($id), Auth::guard('admin')->id());
        flash()->success('Document verified.');
    }

    public function openRejectModal(int $id): void
    {
        $this->rejectDocumentId = $id;
        $this->rejectionReason = '';
    }

    public function rejectDocument(DocumentService $documentService): void
    {
        $this->validate(['rejectionReason' => 'required|string|max:1000']);
        $documentService->reject(
            Document::findOrFail($this->rejectDocumentId),
            $this->rejectionReason,
            Auth::guard('admin')->id()
        );
        $this->rejectDocumentId = null;
        $this->rejectionReason = '';
        flash()->success('Document rejected.');
    }

    public function deleteDocument(int $id): void
    {
        Document::findOrFail($id)->delete();
        flash()->info('Document deleted.');
    }

    protected function createExpiryTask(Document $document): void
    {
        Task::firstOrCreate(
            [
                'task_type' => 'expiry',
                'provider_id' => $document->provider_id,
                'title' => 'Document expiring: ' . $document->title,
            ],
            [
                'description' => 'Expires on ' . $document->expiry_date->format('m/d/Y'),
                'credentialing_case_id' => $document->credentialing_case_id,
                'assigned_admin_id' => Auth::guard('admin')->id(),
                'created_by_admin_id' => Auth::guard('admin')->id(),
                'due_date' => $document->expiry_date,
            ]
        );
    }

    private function resetForm(): void
    {
        $this->formData = [];
        $this->uploadFile = null;
        $this->resetValidation();
    }

    public function render()
    {
        $admin = Auth::guard('admin')->user();
        $scope = app(\App\Services\AdminScopeService::class);
        $query = Document::with(['documentType', 'provider.user', 'practice', 'versions' => fn ($q) => $q->where('is_current', true)]);
        if ($admin) {
            $scope->scopeDocuments($query, $admin);
        }

        if ($this->search) {
            $search = '%' . $this->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                    ->orWhereHas('provider.user', fn ($q) => $q->where('name', 'like', $search));
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

        $documents = $query->latest()->paginate(15);

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
            'stats' => $stats,
            'documentTypes' => DocumentType::where('is_active', true)->orderBy('name')->get(),
            'providers' => $providerQuery->get(),
            'practices' => $practiceQuery->get(['id', 'legal_name']),
            'cases' => $caseQuery->limit(50)->get(),
        ]);
    }
}
