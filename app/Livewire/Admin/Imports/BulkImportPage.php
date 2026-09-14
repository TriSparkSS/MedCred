<?php

namespace App\Livewire\Admin\Imports;

use App\Models\ImportBatch;
use App\Services\SpreadsheetImportService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts::admin', ['title' => 'Bulk Import'])]
class BulkImportPage extends Component
{
    use WithFileUploads;

    public $practiceFile;

    public $providerFile;

    public $lastBatch = null;

    public function importPractices(SpreadsheetImportService $service): void
    {
        $this->validate([
            'practiceFile' => $this->fileRules(),
        ]);

        $this->lastBatch = $service->importPractices($this->practiceFile, Auth::guard('admin')->id());
        $this->practiceFile = null;

        flash()->success("Practice import complete: {$this->lastBatch->success_rows} succeeded, {$this->lastBatch->failed_rows} failed.");
    }

    public function importProviders(SpreadsheetImportService $service): void
    {
        $this->validate([
            'providerFile' => $this->fileRules(),
        ]);

        $this->lastBatch = $service->importProviders($this->providerFile, Auth::guard('admin')->id());
        $this->providerFile = null;

        flash()->success("Provider import complete: {$this->lastBatch->success_rows} succeeded, {$this->lastBatch->failed_rows} failed.");
    }

    public function downloadPracticeTemplate(SpreadsheetImportService $service)
    {
        return $service->downloadPracticeTemplate();
    }

    public function downloadProviderTemplate(SpreadsheetImportService $service)
    {
        return $service->downloadProviderTemplate();
    }

    protected function fileRules(): array
    {
        return ['required', 'file', 'extensions:xlsx,xls,csv,txt', 'max:10240'];
    }

    public function render()
    {
        return view('livewire.admin.imports.bulk-import-page', [
            'batches' => ImportBatch::with('admin')->latest()->limit(10)->get(),
            'practiceColumns' => SpreadsheetImportService::PRACTICE_COLUMNS,
            'providerColumns' => SpreadsheetImportService::PROVIDER_COLUMNS,
        ]);
    }
}
