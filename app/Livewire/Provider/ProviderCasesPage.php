<?php

namespace App\Livewire\Provider;

use App\Models\CredentialingCase;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::provider', ['title' => 'My Applications'])]
class ProviderCasesPage extends Component
{
    use WithPagination;

    public function render()
    {
        abort_unless(can_do('portal.cases.view'), 403);

        $providerId = Auth::guard('web')->user()->providerDetails->id;

        $cases = CredentialingCase::where('provider_id', $providerId)
            ->with(['payer', 'status', 'documentItems'])
            ->latest()
            ->paginate(10);

        return view('livewire.provider.provider-cases-page', compact('cases'));
    }
}
