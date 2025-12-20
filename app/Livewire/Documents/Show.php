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
        
        // Prevent cross-branch document access (IDOR protection)
        $user = auth()->user();
        if ($user && $user->branch_id && $document->branch_id && $user->branch_id !== $document->branch_id) {
            abort(403, 'You cannot access documents from other branches.');
        }
        
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

        return $this->documentService->downloadDocument(
            $this->document,
            auth()->user(),
            request()->boolean('inline', false)
        );
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
            ->when($this->document->branch_id, fn ($q) => $q->where('branch_id', $this->document->branch_id))
            ->when(! $this->document->branch_id && auth()->user()?->branch_id, fn ($q) => $q->where('branch_id', auth()->user()->branch_id))
            ->orderBy('name')
            ->get();

        return view('livewire.documents.show', [
            'users' => $users,
        ]);
    }
}
