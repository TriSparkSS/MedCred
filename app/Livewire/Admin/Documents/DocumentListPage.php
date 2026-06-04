<?php

namespace App\Livewire\Admin\Documents;

use Livewire\Component;
use Livewire\Attributes\Layout;

#[Layout('layouts::admin', ['title' => 'Document List'])]
class DocumentListPage extends Component
{
    public function render()
    {
        return view('livewire.admin.documents.document-list-page');
    }
}
