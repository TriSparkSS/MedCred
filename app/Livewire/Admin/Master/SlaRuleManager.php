<?php

namespace App\Livewire\Admin\Master;

use App\Models\CaseType;
use App\Models\NotificationTemplate;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\SlaRule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin', ['title' => 'SLA Rules | Settings'])]
class SlaRuleManager extends Component
{
    use WithPagination;

    public $search = '';

    public $showModal = false;

    public $modalMode = 'create';

    public $formData = [];

    public $recordId = null;

    public function render()
    {
        $records = SlaRule::with(['notificationTemplate', 'payer', 'practice', 'caseType'])
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%' . $this->search . '%')
                ->orWhere('rule_key', 'like', '%' . $this->search . '%'))
            ->orderBy('name')
            ->paginate(15);

        $notificationTemplates = NotificationTemplate::where('is_active', true)->orderBy('name')->get();
        $payers = Payer::where('is_active', true)->orderBy('name')->get();
        $practices = Practice::orderBy('legal_name')->get();
        $caseTypes = CaseType::where('is_active', true)->orderBy('name')->get();
        $dashboardCategories = $this->dashboardCategories();
        $actions = ['reminder' => 'Reminder', 'escalate' => 'Escalate', 'task' => 'Create Task', 'shift_delay_owner' => 'Shift Delay Owner'];

        return view(
            'livewire.admin.master.sla-rule-manager',
            compact('records', 'notificationTemplates', 'payers', 'practices', 'caseTypes', 'dashboardCategories', 'actions')
        );
    }

    public function updated($propertyName): void
    {
        if ($propertyName === 'search') {
            $this->resetPage();
        }
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->modalMode = 'create';
        $this->formData = [
            'is_active' => true,
            'business_days' => 3,
            'action' => 'reminder',
            'payer_id' => null,
            'practice_id' => null,
            'case_type_id' => null,
        ];
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $record = SlaRule::findOrFail($id);
        $this->recordId = $id;
        $this->modalMode = 'edit';
        $this->formData = $record->only([
            'name',
            'rule_key',
            'payer_id',
            'practice_id',
            'case_type_id',
            'dashboard_category',
            'business_days',
            'action',
            'notification_template_id',
            'is_active',
        ]);
        $this->showModal = true;
    }

    protected function rules(): array
    {
        return [
            'formData.name' => 'required|string|max:255',
            'formData.rule_key' => 'required|string|max:100',
            'formData.payer_id' => 'nullable|exists:payers,id',
            'formData.practice_id' => 'nullable|exists:practices,id',
            'formData.case_type_id' => 'nullable|exists:case_types,id',
            'formData.dashboard_category' => 'nullable|string|max:50',
            'formData.business_days' => 'required|integer|min:1',
            'formData.action' => 'required|string|max:50',
            'formData.notification_template_id' => 'nullable|exists:notification_templates,id',
            'formData.is_active' => 'boolean',
        ];
    }

    public function save(): void
    {
        foreach (['payer_id', 'practice_id', 'case_type_id', 'notification_template_id', 'dashboard_category'] as $nullableKey) {
            if (array_key_exists($nullableKey, $this->formData) && $this->formData[$nullableKey] === '') {
                $this->formData[$nullableKey] = null;
            }
        }

        $this->validate();

        if ($this->modalMode === 'create') {
            SlaRule::create($this->formData);
            flash()->success('SLA rule created successfully!');
        } else {
            SlaRule::findOrFail($this->recordId)->update($this->formData);
            flash()->success('SLA rule updated successfully!');
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $this->recordId = $id;
        sweetalert()->showDenyButton()->info('Are you sure you want to delete this SLA rule?');
    }

    #[On('sweetalert:confirmed')]
    public function onConfirmed(array $payload): void
    {
        SlaRule::findOrFail($this->recordId)->delete();
        $this->recordId = null;
        flash()->info('SLA rule successfully deleted.');
    }

    #[On('sweetalert:denied')]
    public function onDeny(array $payload): void
    {
        $this->recordId = null;
        flash()->info('Deletion cancelled.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->formData = [];
        $this->recordId = null;
        $this->resetValidation();
    }

    private function dashboardCategories(): array
    {
        return [
            'not_started' => 'Not Started',
            'internal' => 'Internal Review',
            'provider' => 'Pending Provider',
            'payer' => 'Pending Payer',
            'approved' => 'Approved',
            'closed' => 'Closed / Denied',
            'on_hold' => 'On Hold',
            'revalidation' => 'Revalidation',
        ];
    }
}
