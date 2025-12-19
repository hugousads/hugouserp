<?php

declare(strict_types=1);

namespace App\Livewire\Components;

use App\Models\Attachment;
use App\Models\Note;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class NotesAttachments extends Component
{
    use WithFileUploads;

    public string $modelType;

    public int $modelId;

    public array $notes = [];

    public array $attachments = [];

    public string $newNote = '';

    public string $noteType = 'general';

    public $newFiles = [];

    public string $fileDescription = '';

    public bool $showNoteModal = false;

    public bool $showFileModal = false;

    public ?int $editingNoteId = null;

    public string $editingNoteContent = '';

    protected $listeners = ['refreshNotesAttachments' => 'loadData'];

    public function mount(string $modelType, int $modelId): void
    {
        $this->modelType = $modelType;
        $this->modelId = $modelId;
        $this->loadData();
    }

    public function loadData(): void
    {
        $this->notes = Note::where('noteable_type', $this->modelType)
            ->where('noteable_id', $this->modelId)
            ->with('creator')
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->get()
            ->toArray();

        $this->attachments = Attachment::where('attachable_type', $this->modelType)
            ->where('attachable_id', $this->modelId)
            ->with('uploader')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($attachment) {
                return array_merge($attachment->toArray(), [
                    'url' => $attachment->url,
                    'human_size' => $attachment->human_size,
                    'is_image' => $attachment->isImage(),
                ]);
            })
            ->toArray();
    }

    public function openNoteModal(): void
    {
        $this->reset(['newNote', 'noteType', 'editingNoteId', 'editingNoteContent']);
        $this->showNoteModal = true;
    }

    public function closeNoteModal(): void
    {
        $this->showNoteModal = false;
        $this->reset(['newNote', 'noteType', 'editingNoteId', 'editingNoteContent']);
    }

    public function saveNote(): void
    {
        $this->validate([
            'newNote' => 'required|string|min:2|max:5000',
        ]);

        $user = Auth::user();

        if ($this->editingNoteId) {
            $note = Note::where('noteable_type', $this->modelType)
                ->where('noteable_id', $this->modelId)
                ->findOrFail($this->editingNoteId);
            $note->update([
                'content' => $this->newNote,
                'type' => $this->noteType,
                'updated_by' => $user?->id,
            ]);
            session()->flash('success', __('Note updated successfully'));
        } else {
            Note::create([
                'noteable_type' => $this->modelType,
                'noteable_id' => $this->modelId,
                'content' => $this->newNote,
                'type' => $this->noteType,
                'branch_id' => $user?->branch_id,
                'created_by' => $user?->id,
            ]);
            session()->flash('success', __('Note added successfully'));
        }

        $this->closeNoteModal();
        $this->loadData();
    }

    public function editNote(int $noteId): void
    {
        $note = Note::where('noteable_type', $this->modelType)
            ->where('noteable_id', $this->modelId)
            ->findOrFail($noteId);
        $this->editingNoteId = $noteId;
        $this->newNote = $note->content;
        $this->noteType = $note->type;
        $this->showNoteModal = true;
    }

    public function deleteNote(int $noteId): void
    {
        Note::where('noteable_type', $this->modelType)
            ->where('noteable_id', $this->modelId)
            ->findOrFail($noteId)
            ->delete();
        session()->flash('success', __('Note deleted successfully'));
        $this->loadData();
    }

    public function togglePin(int $noteId): void
    {
        $note = Note::where('noteable_type', $this->modelType)
            ->where('noteable_id', $this->modelId)
            ->findOrFail($noteId);
        $note->update(['is_pinned' => ! $note->is_pinned]);
        $this->loadData();
    }

    public function openFileModal(): void
    {
        $this->reset(['newFiles', 'fileDescription']);
        $this->showFileModal = true;
    }

    public function closeFileModal(): void
    {
        $this->showFileModal = false;
        $this->reset(['newFiles', 'fileDescription']);
    }

    public function uploadFiles(): void
    {
        $this->validate([
            'newFiles' => 'required|array|min:1',
            'newFiles.*' => 'file|max:10240',
        ]);

        $user = Auth::user();

        foreach ($this->newFiles as $file) {
            $path = $file->store('attachments/'.strtolower(class_basename($this->modelType)), 'public');

            Attachment::create([
                'attachable_type' => $this->modelType,
                'attachable_id' => $this->modelId,
                'filename' => basename($path),
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'disk' => 'public',
                'path' => $path,
                'type' => $this->getFileType($file->getMimeType()),
                'description' => $this->fileDescription,
                'branch_id' => $user?->branch_id,
                'uploaded_by' => $user?->id,
            ]);
        }

        session()->flash('success', __('Files uploaded successfully'));
        $this->closeFileModal();
        $this->loadData();
    }

    public function deleteAttachment(int $attachmentId): void
    {
        $attachment = Attachment::where('attachable_type', $this->modelType)
            ->where('attachable_id', $this->modelId)
            ->findOrFail($attachmentId);

        if (Storage::disk($attachment->disk)->exists($attachment->path)) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }

        $attachment->delete();

        session()->flash('success', __('File deleted successfully'));
        $this->loadData();
    }

    protected function getFileType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }
        if ($mimeType === 'application/pdf') {
            return 'pdf';
        }
        if (str_contains($mimeType, 'spreadsheet') || str_contains($mimeType, 'excel')) {
            return 'spreadsheet';
        }
        if (str_contains($mimeType, 'word') || str_contains($mimeType, 'document')) {
            return 'document';
        }

        return 'other';
    }

    public function render()
    {
        return view('livewire.components.notes-attachments');
    }
}
