<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Services\ImportService;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class BulkImport extends Component
{
    use WithFileUploads;

    public ?string $entityType = null;
    public $importFile = null;
    public bool $hasHeaders = true;
    public bool $updateExisting = false;
    public bool $skipDuplicates = true;
    public int $currentStep = 1;
    public array $previewData = [];
    public array $columnMapping = [];
    public array $importResult = [];
    public bool $importing = false;
    public bool $dryRun = false;

    protected ImportService $importService;

    public function boot(ImportService $importService): void
    {
        $this->importService = $importService;
    }

    public function mount(): void
    {
        $this->entityType = request()->query('type', 'products');
    }

    public function getEntitiesProperty(): array
    {
        return $this->importService->getImportableEntities();
    }

    public function updatedEntityType(): void
    {
        $this->reset(['importFile', 'previewData', 'columnMapping', 'importResult']);
        $this->currentStep = 1;
    }

    public function updatedImportFile(): void
    {
        if ($this->importFile) {
            $this->validate([
                'importFile' => 'required|file|mimes:csv,xlsx,xls|max:10240',
            ]);
            $this->loadPreview();
        }
    }

    protected function loadPreview(): void
    {
        if (!$this->importFile || !$this->entityType) {
            return;
        }

        try {
            $path = $this->importFile->getRealPath();
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            if (empty($rows)) {
                session()->flash('error', __('The file is empty'));
                return;
            }

            $headers = $this->hasHeaders ? array_map('trim', $rows[0]) : [];
            $this->previewData = array_slice($rows, $this->hasHeaders ? 1 : 0, 10);
            
            // Auto-map columns
            $entityConfig = $this->entities[$this->entityType] ?? [];
            $availableColumns = array_merge(
                $entityConfig['required_columns'] ?? [],
                $entityConfig['optional_columns'] ?? []
            );

            $this->columnMapping = [];
            foreach ($headers as $index => $header) {
                $lowerHeader = strtolower(trim($header));
                foreach ($availableColumns as $col) {
                    if (strtolower($col) === $lowerHeader) {
                        $this->columnMapping[$index] = $col;
                        break;
                    }
                }
            }
        } catch (\Exception $e) {
            session()->flash('error', __('Error reading file: ') . $e->getMessage());
        }
    }

    public function nextStep(): void
    {
        if ($this->currentStep === 1) {
            if (!$this->importFile) {
                session()->flash('error', __('Please upload a file first'));
                return;
            }
            if (!$this->entityType) {
                session()->flash('error', __('Please select an entity type'));
                return;
            }
        }

        if ($this->currentStep < 3) {
            $this->currentStep++;
        }
    }

    public function previousStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    public function downloadTemplate(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $path = $this->importService->generateTemplate($this->entityType);
        
        if (!$path) {
            session()->flash('error', __('Failed to generate template'));
            return back();
        }

        return response()->download(
            Storage::disk('local')->path($path),
            "import_template_{$this->entityType}.xlsx"
        )->deleteFileAfterSend(true);
    }

    public function runImport(): void
    {
        if (!$this->importFile || !$this->entityType) {
            session()->flash('error', __('Please upload a file and select entity type'));
            return;
        }

        $this->importing = true;

        try {
            $path = $this->importFile->store('imports', 'local');
            $fullPath = Storage::disk('local')->path($path);

            $this->importResult = $this->importService->import(
                $this->entityType,
                $fullPath,
                [
                    'update_existing' => $this->updateExisting,
                    'skip_duplicates' => $this->skipDuplicates,
                    'branch_id' => auth()->user()->branch_id,
                ]
            );

            // Clean up
            Storage::disk('local')->delete($path);

            if ($this->importResult['success']) {
                session()->flash('success', $this->importResult['message']);
            } else {
                session()->flash('error', $this->importResult['message']);
            }
        } catch (\Exception $e) {
            session()->flash('error', __('Import failed: ') . $e->getMessage());
            $this->importResult = [
                'success' => false,
                'message' => $e->getMessage(),
                'imported' => 0,
                'failed' => 0,
                'errors' => [],
            ];
        } finally {
            $this->importing = false;
        }
    }

    public function render()
    {
        return view('livewire.admin.bulk-import')
            ->layout('layouts.app')
            ->title(__('Bulk Import'));
    }
}
