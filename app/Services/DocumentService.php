<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentService
{
    private string $documentsDisk;
    private array $allowedMimes = [
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'ppt',
        'pptx',
        'png',
        'jpg',
        'jpeg',
        'gif',
        'csv',
        'txt',
    ];

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
        $this->validateFile($file);

        return DB::transaction(function () use ($file, $data) {
            // Store the file on the configured private disk
            $disk = $this->documentsDisk;
            $path = $file->store('documents', $disk);
            $isPublic = (bool) ($data['is_public'] ?? false);

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
                'is_public' => $isPublic,
                'access_level' => $isPublic ? 'public' : 'private',
                'version' => 1,
                'version_number' => 1,
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
                'metadata' => $data['metadata'] ?? null,
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
        $this->validateFile($file);

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
                'version_number' => $nextVersion,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'file_type' => $file->getClientOriginalExtension(),
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
        // Prevent cross-branch document updates (IDOR protection)
        $user = auth()->user();
        if ($user && $user->branch_id && $document->branch_id && $user->branch_id !== $document->branch_id) {
            throw new AuthorizationException('You cannot update documents from other branches.');
        }
        
        return DB::transaction(function () use ($document, $data) {
            $document->update([
                'title' => $data['title'] ?? $document->title,
                'description' => $data['description'] ?? $document->description,
                'folder' => $data['folder'] ?? $document->folder,
                'category' => $data['category'] ?? $document->category,
                'is_public' => $data['is_public'] ?? $document->is_public,
                'access_level' => ($data['is_public'] ?? $document->is_public) ? 'public' : 'private',
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
        $this->ensureCanManageShares($document);
        $targetUser = User::findOrFail($userId);

        if ($document->branch_id && $targetUser->branch_id !== $document->branch_id) {
            throw new AuthorizationException('You cannot share documents across branches.');
        }

        if ($document->branch_id && auth()->user()?->branch_id && auth()->user()->branch_id !== $document->branch_id) {
            throw new AuthorizationException('You cannot share documents outside your branch.');
        }

        DB::transaction(function () use ($document, $userId, $permission, $expiresAt) {
            $document->shares()->updateOrCreate(
                ['user_id' => $userId],
                [
                    'shared_by' => auth()->id(),
                    'shared_with_user_id' => $userId,
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
        $this->ensureCanManageShares($document);

        DB::transaction(function () use ($document, $userId) {
            $document->shares()
                ->where(function ($query) use ($userId) {
                    $query->where('user_id', $userId)
                        ->orWhere('shared_with_user_id', $userId);
                })
                ->delete();

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
    public function downloadDocument(Document $document, User $user, bool $inline = false): StreamedResponse
    {
        if (! $user->can('documents.download')) {
            abort(403, 'You do not have permission to download this document');
        }

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
        $share ??= $document->shares()->where('shared_with_user_id', $user->id)->first();
        if ($share) {
            $share->incrementAccessCount();
        }

        $disk = $this->resolveDisk($document->file_path);

        // Ensure the file exists on the resolved disk to avoid storage driver errors
        abort_unless(
            Storage::disk($disk)->exists($document->file_path),
            404,
            'File not found'
        );

        $headers = ['Content-Type' => $document->mime_type];

        if ($inline) {
            return Storage::disk($disk)->response(
                $document->file_path,
                $document->file_name,
                $headers
            );
        }

        return Storage::disk($disk)->download(
            $document->file_path,
            $document->file_name,
            $headers
        );
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

    private function validateFile(UploadedFile $file): void
    {
        Validator::make(
            ['file' => $file],
            ['file' => 'required|file|max:51200|mimes:' . implode(',', $this->allowedMimes)]
        )->validate();
    }

    private function ensureCanManageShares(Document $document): void
    {
        $user = auth()->user();

        if (!$user || ($document->uploaded_by !== $user->id && !$user->can('documents.manage'))) {
            throw new AuthorizationException('You are not allowed to manage shares for this document.');
        }
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
