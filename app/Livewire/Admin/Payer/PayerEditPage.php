<?php

namespace App\Livewire\Admin\Payer;

use App\Models\AuditLog;
use App\Models\DocumentType;
use App\Models\Payer;
use App\Models\PayerContact;
use App\Models\PayerDocumentRequirement;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'Edit Payer'])]
class PayerEditPage extends Component
{
    public $payerId;

    public $formData = [];

    public $documentRequirements = [];

    public array $contacts = [];

    protected function rules(): array
    {
        return [
            'formData.name' => 'required|string|max:255|unique:payers,name,' . $this->payerId,
            'formData.states_applicable' => 'nullable|string|max:500',
            'formData.application_type' => 'nullable|string|max:100',
            'formData.submission_channel' => 'nullable|string|max:50',
            'formData.portal_url' => 'nullable|url|max:255',
            'formData.portal_notes' => 'nullable|string|max:1000',
            'formData.fax' => 'nullable|string|max:30',
            'formData.email' => 'nullable|email|max:255',
            'formData.phone' => 'nullable|string|max:30',
            'formData.turnaround_days' => 'nullable|integer|min:1|max:365',
            'formData.participation_rules' => 'nullable|string|max:2000',
            'formData.is_active' => 'boolean',
            'documentRequirements.*.document_type_id' => 'nullable|exists:document_types,id',
            'documentRequirements.*.state' => 'nullable|string|max:10',
            'documentRequirements.*.is_required' => 'boolean',
            'contacts.*.name' => 'nullable|string|max:255',
            'contacts.*.role' => 'nullable|string|max:100',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.phone' => 'nullable|string|max:30',
            'contacts.*.state_region' => 'nullable|string|max:100',
            'contacts.*.escalation_level' => 'nullable|string|max:50',
            'contacts.*.notes' => 'nullable|string|max:500',
        ];
    }

    public function mount($payer): void
    {
        $payerModel = Payer::with(['documentRequirements', 'contacts'])->findOrFail($payer);
        $this->payerId = $payerModel->id;

        $this->formData = $payerModel->only([
            'name', 'states_applicable', 'application_type', 'submission_channel',
            'portal_url', 'portal_notes', 'fax', 'email', 'phone',
            'turnaround_days', 'participation_rules', 'is_active',
        ]);

        $this->documentRequirements = $payerModel->documentRequirements->map(fn ($req) => [
            'document_type_id' => $req->document_type_id,
            'state' => $req->state ?? '',
            'is_required' => $req->is_required,
        ])->toArray();

        if (empty($this->documentRequirements)) {
            $this->addDocumentRequirement();
        }

        $this->contacts = $payerModel->contacts->map(fn ($contact) => [
            'name' => $contact->name ?? '',
            'role' => $contact->role ?? ($contact->contact_type !== 'general' ? $contact->contact_type : '') ?? '',
            'email' => $contact->email ?? '',
            'phone' => $contact->phone ?? '',
            'state_region' => $contact->state_region ?? '',
            'escalation_level' => $contact->escalation_level ?? '',
            'notes' => $contact->notes ?? '',
            'is_primary' => (bool) ($contact->is_primary ?? false),
        ])->toArray();

        if (empty($this->contacts)) {
            $this->addContact();
        }
    }

    public function addDocumentRequirement(): void
    {
        $this->documentRequirements[] = [
            'document_type_id' => '',
            'state' => '',
            'is_required' => true,
        ];
    }

    public function removeDocumentRequirement(int $index): void
    {
        unset($this->documentRequirements[$index]);
        $this->documentRequirements = array_values($this->documentRequirements);
    }

    public function addContact(): void
    {
        $this->contacts[] = [
            'name' => '',
            'role' => '',
            'email' => '',
            'phone' => '',
            'state_region' => '',
            'escalation_level' => '',
            'notes' => '',
            'is_primary' => empty($this->contacts),
        ];
    }

    public function removeContact(int $index): void
    {
        unset($this->contacts[$index]);
        $this->contacts = array_values($this->contacts);
    }

    public function save()
    {
        $this->validate();

        $payer = Payer::findOrFail($this->payerId);
        $payer->update($this->formData);
        $payer->documentRequirements()->delete();
        $this->syncDocumentRequirements($payer);

        $payer->contacts()->delete();
        $this->syncContacts($payer);

        AuditLog::record('payer.updated', $payer, auth('admin')->id());

        flash()->success('Payer updated successfully!');

        return redirect()->route('admin.payers');
    }

    protected function syncDocumentRequirements(Payer $payer): void
    {
        foreach ($this->documentRequirements as $req) {
            if (empty($req['document_type_id'])) {
                continue;
            }

            PayerDocumentRequirement::create([
                'payer_id' => $payer->id,
                'document_type_id' => $req['document_type_id'],
                'state' => $req['state'] ?: null,
                'is_required' => $req['is_required'] ?? true,
            ]);
        }
    }

    protected function syncContacts(Payer $payer): void
    {
        foreach ($this->contacts as $index => $contact) {
            $name = trim((string) ($contact['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $payload = [
                'payer_id' => $payer->id,
                'name' => $name,
                'email' => filled($contact['email'] ?? null) ? trim((string) $contact['email']) : null,
                'phone' => filled($contact['phone'] ?? null) ? trim((string) $contact['phone']) : null,
                'contact_type' => filled($contact['role'] ?? null) ? trim((string) $contact['role']) : 'general',
                'is_primary' => (bool) ($contact['is_primary'] ?? ($index === 0)),
            ];

            foreach (['role', 'state_region', 'escalation_level', 'notes'] as $optionalCol) {
                if (Schema::hasColumn('payer_contacts', $optionalCol)) {
                    $payload[$optionalCol] = filled($contact[$optionalCol] ?? null) ? trim((string) $contact[$optionalCol]) : null;
                }
            }

            PayerContact::forceCreate($payload);
        }
    }

    public function render()
    {
        $documentTypes = DocumentType::where('is_active', true)->orderBy('name')->get();
        $submissionChannels = ['portal', 'fax', 'email', 'mail', 'mixed'];
        $applicationTypes = ['credentialing', 'participation', 'reassignment', 'eft', 'era', 'roster', 'group', 'recredentialing'];

        return view('livewire.admin.payer.payer-edit-page', compact('documentTypes', 'submissionChannels', 'applicationTypes'));
    }
}
