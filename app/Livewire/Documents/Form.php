<?php

declare(strict_types=1);

namespace App\Livewire\Documents;

use App\Models\Document;
use App\Models\DocumentTag;
use App\Services\DocumentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Form extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public ?Document $document = null;

    public bool $isEdit = false;

    public string $title = '';

    // Nullable to match database schema and prevent type errors when filling from model
    public ?string $description = null;

    public ?UploadedFile $file = null;

    // Nullable to match database schema and prevent type errors when filling from model
    public ?string $folder = null;

    // Nullable to match database schema and prevent type errors when filling from model
    public ?string $category = null;

    public bool $is_public = false;

    public array $selectedTags = [];

    protected DocumentService $documentService;

    public function boot(DocumentService $documentService): void
    {
        $this->documentService = $documentService;
    }

    public function mount(?Document $document = null): void
    {
        if ($document && $document->exists) {
            $this->authorize('documents.edit');
            
            // Prevent cross-branch document access (IDOR protection)
            $user = auth()->user();
            if ($user && $user->branch_id && $document->branch_id && $user->branch_id !== $document->branch_id) {
                abort(403, 'You cannot access documents from other branches.');
            }
            
            $this->isEdit = true;
            $this->document = $document;
            $this->fill($document->only([
                'title',
                'description',
                'folder',
                'category',
                'is_public',
            ]));
            $this->selectedTags = $document->tags->pluck('id')->toArray();
        } else {
            $this->authorize('documents.create');
        }
    }

    public function save(): RedirectResponse
    {
        if ($this->isEdit) {
            $this->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'folder' => 'nullable|string|max:255',
                'category' => 'nullable|string|max:100',
            ]);

            $this->document = $this->documentService->updateDocument($this->document, [
                'title' => $this->title,
                'description' => $this->description,
                'folder' => $this->folder,
                'category' => $this->category,
                'is_public' => $this->is_public,
                'tags' => $this->selectedTags,
            ]);

            session()->flash('success', __('Document updated successfully'));
        } else {
            $this->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'file' => 'required|file|max:51200|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,png,jpg,jpeg,gif,csv,txt',
                'folder' => 'nullable|string|max:255',
                'category' => 'nullable|string|max:100',
            ]);

            $this->document = $this->documentService->uploadDocument($this->file, [
                'title' => $this->title,
                'description' => $this->description,
                'folder' => $this->folder,
                'category' => $this->category,
                'is_public' => $this->is_public,
                'tags' => $this->selectedTags,
            ]);

            session()->flash('success', __('Document uploaded successfully'));
        }

        return redirect()->route('app.documents.show', $this->document->id);
    }

    public function render()
    {
        $tags = DocumentTag::all();

        return view('livewire.documents.form', [
            'tags' => $tags,
        ]);
    }
}
