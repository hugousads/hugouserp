<?php

declare(strict_types=1);

namespace App\Livewire\Documents;

use App\Models\Document;
use App\Services\DocumentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Versions extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public Document $document;
    public ?\Illuminate\Http\UploadedFile $file = null;
    public string $changeNotes = '';

    protected DocumentService $documentService;

    public function boot(DocumentService $documentService): void
    {
        $this->documentService = $documentService;
    }

    public function mount(Document $document): void
    {
        $this->authorize('documents.versions.manage');
        $this->document = $document->load(['versions.uploader']);

        // Check if user can access this document
        if (!$document->canBeAccessedBy(auth()->user())) {
            abort(403, 'You do not have permission to manage versions for this document');
        }
    }

    public function uploadVersion(): void
    {
        $this->validate([
            'file' => 'required|file|max:51200|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,png,jpg,jpeg,gif,csv,txt',
            'changeNotes' => 'nullable|string',
        ]);

        $this->documentService->uploadVersion($this->document, $this->file, $this->changeNotes);

        session()->flash('success', __('New version uploaded successfully'));
        $this->document->refresh();
        $this->reset(['file', 'changeNotes']);
    }

    public function render()
    {
        return view('livewire.documents.versions');
    }
}
