<?php

namespace App\Livewire\Admin\Payer;

use App\Models\DocumentType;
use App\Models\Payer;
use App\Models\PayerDocumentRequirement;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'Edit Payer'])]
class PayerEditPage extends Component
{
    public $payerId;

    public $formData = [];

    public $documentRequirements = [];

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
        ];
    }

    public function mount($payer): void
    {
        $payerModel = Payer::with('documentRequirements')->findOrFail($payer);
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

    public function save()
    {
        $this->validate();

        $payer = Payer::findOrFail($this->payerId);
        $payer->update($this->formData);
        $payer->documentRequirements()->delete();
        $this->syncDocumentRequirements($payer);

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

    public function render()
    {
        $documentTypes = DocumentType::where('is_active', true)->orderBy('name')->get();
        $submissionChannels = ['portal', 'fax', 'email', 'mail', 'mixed'];
        $applicationTypes = ['credentialing', 'participation', 'reassignment', 'eft', 'era', 'roster', 'group', 'recredentialing'];

        return view('livewire.admin.payer.payer-edit-page', compact('documentTypes', 'submissionChannels', 'applicationTypes'));
    }
}
