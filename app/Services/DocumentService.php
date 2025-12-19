<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentService
{
    private string $documentsDisk;

    public function __construct(
        protected UIHelperService $uiHelper
    ) {
        $this->documentsDisk = (string) config('filesystems.document_disk', 'local');
    }
    /**
     * Upload a new document
     */
    public function uploadDocument(UploadedFile $file, array $data): Document
    {
        return DB::transaction(function () use ($file, $data) {
            // Store the file on the configured private disk
            $disk = $this->documentsDisk;
            $path = $file->store('documents', $disk);

            // Create document record
            $document = Document::create([
                'code' => $data['code'] ?? Str::uuid()->toString(),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'file_type' => $file->getClientOriginalExtension(),
                'mime_type' => $file->getMimeType(),
                'folder' => $data['folder'] ?? null,
                'category' => $data['category'] ?? null,
                'is_public' => $data['is_public'] ?? false,
                'uploaded_by' => auth()->id(),
                'branch_id' => $data['branch_id'] ?? auth()->user()->branch_id,
                'metadata' => $data['metadata'] ?? null,
            ]);

            // Create initial version
            DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => 1,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'uploaded_by' => auth()->id(),
                'change_notes' => 'Initial upload',
            ]);

            // Log activity
            $document->logActivity('created', auth()->user());

            // Attach tags if provided
            if (!empty($data['tags'])) {
                $document->tags()->sync($data['tags']);
            }

            return $document;
        });
    }

    /**
     * Upload a new version of existing document
     */
    public function uploadVersion(Document $document, UploadedFile $file, ?string $changeNotes = null): DocumentVersion
    {
        return DB::transaction(function () use ($document, $file, $changeNotes) {
            // Store the file on the configured private disk
            $disk = $this->documentsDisk;
            $path = $file->store('documents', $disk);

            // Get next version number
            $nextVersion = $document->versions()->max('version_number') + 1;

            // Create version record
            $version = DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => $nextVersion,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'uploaded_by' => auth()->id(),
                'change_notes' => $changeNotes,
            ]);

            // Update document with new version info
            $document->update([
                'version' => $nextVersion,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ]);

            // Log activity
            $document->logActivity('version_created', auth()->user(), [
                'version' => $nextVersion,
                'change_notes' => $changeNotes,
            ]);

            return $version;
        });
    }

    /**
     * Update document metadata
     */
    public function updateDocument(Document $document, array $data): Document
    {
        return DB::transaction(function () use ($document, $data) {
            $document->update([
                'title' => $data['title'] ?? $document->title,
                'description' => $data['description'] ?? $document->description,
                'folder' => $data['folder'] ?? $document->folder,
                'category' => $data['category'] ?? $document->category,
                'is_public' => $data['is_public'] ?? $document->is_public,
                'metadata' => $data['metadata'] ?? $document->metadata,
            ]);

            // Update tags if provided
            if (isset($data['tags'])) {
                $document->tags()->sync($data['tags']);
            }

            // Log activity
            $document->logActivity('updated', auth()->user());

            return $document->fresh();
        });
    }

    /**
     * Share document with user
     */
    public function shareDocument(Document $document, int $userId, string $permission = 'view', ?\DateTime $expiresAt = null): void
    {
        DB::transaction(function () use ($document, $userId, $permission, $expiresAt) {
            $document->shares()->updateOrCreate(
                ['user_id' => $userId],
                [
                    'shared_by' => auth()->id(),
                    'permission' => $permission,
                    'expires_at' => $expiresAt,
                ]
            );

            // Log activity
            $document->logActivity('shared', auth()->user(), [
                'shared_with_user_id' => $userId,
                'permission' => $permission,
            ]);
        });
    }

    /**
     * Unshare document from user
     */
    public function unshareDocument(Document $document, int $userId): void
    {
        DB::transaction(function () use ($document, $userId) {
            $document->shares()->where('user_id', $userId)->delete();

            // Log activity
            $document->logActivity('unshared', auth()->user(), [
                'unshared_from_user_id' => $userId,
            ]);
        });
    }

    /**
     * Delete document
     */
    public function deleteDocument(Document $document): bool
    {
        return DB::transaction(function () use ($document) {
            // Delete all file versions from storage
            $documentDisk = $this->resolveDisk($document->file_path);
            Storage::disk($documentDisk)->delete($document->file_path);

            foreach ($document->versions as $version) {
                $versionDisk = $this->resolveDisk($version->file_path);
                Storage::disk($versionDisk)->delete($version->file_path);
            }

            // Log activity before deletion
            $document->logActivity('deleted', auth()->user());

            // Soft delete the document (cascades to versions, shares, activities)
            return $document->delete();
        });
    }

    /**
     * Download document and log activity
     */
    public function downloadDocument(Document $document, User $user): string
    {
        // Check access
        if (!$document->canBeAccessedBy($user)) {
            abort(403, 'You do not have permission to download this document');
        }

        // Log activity
        $document->logActivity('downloaded', $user, [
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Increment access count if shared
        $share = $document->shares()->where('user_id', $user->id)->first();
        if ($share) {
            $share->incrementAccessCount();
        }

        $disk = $this->resolveDisk($document->file_path);

        return Storage::disk($disk)->path($document->file_path);
    }

    public function documentsDisk(): string
    {
        return $this->documentsDisk;
    }

    private function resolveDisk(string $path): string
    {
        $primaryDisk = $this->documentsDisk;

        if (Storage::disk($primaryDisk)->exists($path)) {
            return $primaryDisk;
        }

        return 'public';
    }

    /**
     * Get document statistics
     */
    public function getStatistics(?int $branchId = null): array
    {
        $baseQuery = Document::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $totalSize = (clone $baseQuery)->sum('file_size');

        return [
            'total_documents' => (clone $baseQuery)->count(),
            'total_size' => $totalSize,
            'total_size_formatted' => $this->uiHelper->formatBytes((int) $totalSize),
            'by_category' => (clone $baseQuery)
                ->select('category', DB::raw('count(*) as count'))
                ->whereNotNull('category')
                ->groupBy('category')
                ->pluck('count', 'category')
                ->toArray(),
            'storage_by_type' => (clone $baseQuery)
                ->select('mime_type',
                    DB::raw('COUNT(*) as documents'),
                    DB::raw('COALESCE(SUM(file_size), 0) as total_size'))
                ->whereNotNull('mime_type')
                ->groupBy('mime_type')
                ->orderByDesc('total_size')
                ->get(),
            'recent_uploads' => (clone $baseQuery)->latest()->limit(5)->get(),
            'top_uploaders' => (clone $baseQuery)
                ->select('uploaded_by',
                    DB::raw('COUNT(*) as documents_uploaded'),
                    DB::raw('COALESCE(SUM(file_size), 0) as storage_used'))
                ->whereNotNull('uploaded_by')
                ->groupBy('uploaded_by')
                ->orderByDesc('documents_uploaded')
                ->limit(5)
                ->with('uploader:id,name')
                ->get(),
            'most_downloaded' => Document::withCount('activities')
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->orderBy('activities_count', 'desc')
                ->limit(5)
                ->get(),
        ];
    }
}
