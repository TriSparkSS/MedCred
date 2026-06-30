<?php

namespace App\Livewire\Practice;

use App\Services\PortalActionItemsService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::practice', ['title' => 'Action Items'])]
class PracticeActionItemsPage extends Component
{
    public function render(PortalActionItemsService $actionItems)
    {
        abort_unless(can_do('portal.action_items.view'), 403);

        $practice = Auth::guard('web')->user()->practice;

        $items = $actionItems->practiceItems($practice);

        return view('livewire.practice.practice-action-items-page', [
            'practice' => $practice,
            'items' => $items,
        ]);
    }
}
