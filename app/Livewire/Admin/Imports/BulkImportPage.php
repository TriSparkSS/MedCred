<?php

namespace App\Livewire\Admin\Imports;

use App\Models\ImportBatch;
use App\Services\CsvImportService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts::admin', ['title' => 'Bulk Import'])]
class BulkImportPage extends Component
{
    use WithFileUploads;

    public $importFile;

    public $lastBatch = null;

    public function importProviders(CsvImportService $service): void
    {
        $this->validate([
            'importFile' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $this->lastBatch = $service->importProviders($this->importFile, Auth::guard('admin')->id());
        $this->importFile = null;

        flash()->success("Import complete: {$this->lastBatch->success_rows} succeeded, {$this->lastBatch->failed_rows} failed.");
    }

    public function render()
    {
        return view('livewire.admin.imports.bulk-import-page', [
            'batches' => ImportBatch::with('admin')->latest()->limit(10)->get(),
        ]);
    }
}
