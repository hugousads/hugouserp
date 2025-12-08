<?php

declare(strict_types=1);

namespace App\Livewire\Documents;

use App\Models\Document;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    use AuthorizesRequests;

    public Document $document;
    public int $shareUserId = 0;
    public string $sharePermission = 'view';
    public ?string $shareExpiresAt = null;

    protected DocumentService $documentService;

    public function boot(DocumentService $documentService): void
    {
        $this->documentService = $documentService;
    }

    public function mount(Document $document): void
    {
        $this->authorize('documents.view');
        $this->document = $document->load(['uploader', 'tags', 'versions.uploader', 'shares.user', 'activities.user']);

        // Check if user can access this document
        if (!$document->canBeAccessedBy(auth()->user())) {
            abort(403, 'You do not have permission to view this document');
        }

        // Log view activity
        $document->logActivity('viewed', auth()->user());
    }

    public function download()
    {
        $this->authorize('documents.download');

        $path = $this->documentService->downloadDocument($this->document, auth()->user());

        return response()->download($path, $this->document->file_name);
    }

    public function shareDocument(): void
    {
        $this->authorize('documents.share');

        $this->validate([
            'shareUserId' => 'required|exists:users,id',
            'sharePermission' => 'required|in:view,edit,full',
            'shareExpiresAt' => 'nullable|date|after:now',
        ]);

        $expiresAt = $this->shareExpiresAt ? new \DateTime($this->shareExpiresAt) : null;

        $this->documentService->shareDocument(
            $this->document,
            $this->shareUserId,
            $this->sharePermission,
            $expiresAt
        );

        session()->flash('success', __('Document shared successfully'));
        $this->document->refresh();
        $this->reset(['shareUserId', 'sharePermission', 'shareExpiresAt']);
    }

    public function unshare(int $userId): void
    {
        $this->authorize('documents.share');

        $this->documentService->unshareDocument($this->document, $userId);

        session()->flash('success', __('Access revoked successfully'));
        $this->document->refresh();
    }

    public function render()
    {
        $users = User::where('id', '!=', $this->document->uploaded_by)
            ->orderBy('name')
            ->get();

        return view('livewire.documents.show', [
            'users' => $users,
        ]);
    }
}
