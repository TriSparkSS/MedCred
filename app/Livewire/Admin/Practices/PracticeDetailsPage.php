<?php

namespace App\Livewire\Admin\Practices;

use App\Models\Practice;
use App\Services\AdminScopeService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'Practice Details'])]
class PracticeDetailsPage extends Component
{
    public $practice;

    public $activeTab = 'overview';

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function mount($practice, AdminScopeService $scope): void
    {
        $this->practice = Practice::with([
            'user',
            'addresses',
            'contacts',
            'locations',
            'assignedAdmins',
            'providers.user',
            'providers.specialty',
            'credentialingCases.payer',
            'credentialingCases.status',
            'credentialingCases.provider.user',
            'credentialingCases.delayOwner',
            'credentialingCases.assignedAdmin',
            'credentialingCases.activities.admin',
            'credentialingCases.activities.user',
            'credentialingCases.activities.credentialingCase',
            'documents.documentType',
            'documents.versions' => fn ($q) => $q->where('is_current', true),
        ])->findOrFail($practice);

        $admin = Auth::guard('admin')->user();
        if ($admin && ! $scope->canAccessPractice($admin, (int) $this->practice->id)) {
            abort(403, 'You do not have access to this practice.');
        }
    }

    public function render()
    {
        $cases = $this->practice->credentialingCases;
        $closedCategories = ['approved', 'closed'];

        $stats = [
            'linked_providers' => $this->practice->providers->count(),
            'active_cases' => $cases->filter(fn ($c) => ! in_array($c->status?->dashboard_category, $closedCategories))->count(),
            'locations' => $this->practice->locations->count(),
            'contacts' => $this->practice->contacts->count(),
        ];

        $timelineActivities = $cases
            ->flatMap(fn ($case) => $case->activities)
            ->sortByDesc('created_at')
            ->take(50);

        return view('livewire.admin.practices.practice-details-page', compact('stats', 'timelineActivities'));
    }
}
