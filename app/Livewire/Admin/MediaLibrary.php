<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Media;
use App\Services\ImageOptimizationService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class MediaLibrary extends Component
{
    use WithFileUploads, WithPagination;

    public $files = [];
    public string $search = '';
    public string $filterType = 'all'; // all, images, documents
    public string $filterOwner = 'all'; // all, mine

    protected $queryString = ['search', 'filterType', 'filterOwner'];

    public function mount(): void
    {
        $user = auth()->user();
        if (!$user || !$user->can('media.view')) {
            abort(403, __('Unauthorized access to media library'));
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFiles(): void
    {
        $this->validate([
            'files.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt', // 10MB max, restricted types
        ]);

        $user = auth()->user();
        if (!$user->can('media.upload')) {
            session()->flash('error', __('You do not have permission to upload files'));
            return;
        }

        $optimizationService = app(ImageOptimizationService::class);

        foreach ($this->files as $file) {
            $result = $optimizationService->optimizeUploadedFile($file, 'general', 'public');

            Media::create([
                'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $result['file_path'],
                'thumbnail_path' => $result['thumbnail_path'],
                'mime_type' => $result['mime_type'],
                'extension' => $result['extension'],
                'size' => $result['size'],
                'optimized_size' => $result['optimized_size'],
                'width' => $result['width'],
                'height' => $result['height'],
                'disk' => 'public',
                'collection' => 'general',
                'user_id' => $user->id,
                'branch_id' => $user->branch_id,
            ]);
        }

        $this->files = [];
        session()->flash('success', __('Files uploaded successfully'));
    }

    public function delete(int $id): void
    {
        $user = auth()->user();
        $media = Media::findOrFail($id);

        // Check permissions
        $canDelete = $user->can('media.manage') || 
                     ($user->can('media.delete') && $media->user_id === $user->id);

        if (!$canDelete) {
            session()->flash('error', __('You do not have permission to delete this file'));
            return;
        }

        // Delete files from storage
        Storage::disk($media->disk)->delete($media->file_path);
        if ($media->thumbnail_path) {
            Storage::disk($media->disk)->delete($media->thumbnail_path);
        }

        $media->delete();
        session()->flash('success', __('File deleted successfully'));
    }

    public function render()
    {
        $user = auth()->user();
        
        $query = Media::query()
            ->with('user')
            ->when($this->search, fn ($q) =>
                $q->where(function ($query) {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('original_name', 'like', "%{$this->search}%");
                })
            )
            ->when($this->filterType === 'images', fn ($q) => $q->images())
            ->when($this->filterType === 'documents', fn ($q) => $q->documents())
            ->when($this->filterOwner === 'mine' || !$user->can('media.view-others'), fn ($q) => 
                $q->forUser($user->id)
            )
            ->orderBy('created_at', 'desc');

        $media = $query->paginate(20);

        return view('livewire.admin.media-library', [
            'media' => $media,
        ])->layout('layouts.app', ['title' => __('Media Library')]);
    }
}
