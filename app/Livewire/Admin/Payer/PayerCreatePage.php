<?php

namespace App\Livewire\Admin\Payer;

use App\Models\DocumentType;
use App\Models\Payer;
use App\Models\PayerDocumentRequirement;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'Add Payer'])]
class PayerCreatePage extends Component
{
    public $formData = ['is_active' => true];

    public $documentRequirements = [];

    protected function rules(): array
    {
        return [
            'formData.name' => 'required|string|max:255|unique:payers,name',
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

    public function mount(): void
    {
        $this->addDocumentRequirement();
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

        $payer = Payer::create($this->formData);
        $this->syncDocumentRequirements($payer);

        flash()->success('Payer created successfully!');

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

        return view('livewire.admin.payer.payer-create-page', compact('documentTypes', 'submissionChannels', 'applicationTypes'));
    }
}
