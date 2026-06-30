<?php

namespace App\Livewire\Practice;

use App\Models\CredentialingCase;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::practice', ['title' => 'Practice Applications'])]
class PracticeCasesPage extends Component
{
    use WithPagination;

    public function render()
    {
        abort_unless(can_do('portal.cases.view'), 403);

        $practiceId = Auth::guard('web')->user()->practice->id;

        $cases = CredentialingCase::where('practice_id', $practiceId)
            ->with(['payer', 'status', 'documentItems', 'provider.user'])
            ->latest()
            ->paginate(10);

        return view('livewire.practice.practice-cases-page', compact('cases'));
    }
}
