<?php

declare(strict_types=1);

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    /**
     * Backward-compatible alias for existing integrations.
     */
    public function store(Request $request)
    {
        return $this->upload($request);
    }

    /**
     * Download a stored file after validating authorization and path safety.
     */
    public function show(Request $request, string $fileId)
    {
        Gate::authorize('files.view');

        [$disk, $path] = [$this->resolveDisk($request), $this->normalizePath($fileId)];

        if (! Storage::disk($disk)->exists($path)) {
            return $this->fail(__('File not found.'), 404);
        }

        return Storage::disk($disk)->download($path);
    }

    /**
     * Delete a stored file after validating authorization and path safety.
     */
    public function delete(Request $request, string $fileId): JsonResponse
    {
        Gate::authorize('files.delete');

        [$disk, $path] = [$this->resolveDisk($request), $this->normalizePath($fileId)];

        if (! Storage::disk($disk)->exists($path)) {
            return $this->fail(__('File not found.'), 404);
        }

        Storage::disk($disk)->delete($path);

        return $this->ok([ 'deleted' => true, 'disk' => $disk, 'path' => $path ], __('File deleted successfully'));
    }

    /**
     * Return file metadata without exposing file contents.
     */
    public function meta(Request $request, string $fileId): JsonResponse
    {
        Gate::authorize('files.view');

        [$disk, $path] = [$this->resolveDisk($request), $this->normalizePath($fileId)];
        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            return $this->fail(__('File not found.'), 404);
        }

        $mime = $storage->mimeType($path);
        $size = $storage->size($path);
        $visibility = method_exists($storage, 'getVisibility') ? $storage->getVisibility($path) : null;

        return $this->ok([
            'disk' => $disk,
            'path' => $path,
            'mime' => $mime,
            'size' => $size,
            'visibility' => $visibility,
            'last_modified' => $storage->lastModified($path),
            'url' => $this->buildUrl($storage, $path),
        ], __('File metadata retrieved successfully'));
    }

    /**
     * Store an uploaded file and return its path/url.
     *
     * Security features:
     * - File type validation against whitelist (SVG removed)
     * - File size limits enforced
     * - Random filename generation to prevent overwriting
     * - MIME type verification
     * - Extension validation
     * - Private visibility by default
     *
     * Accepts: file, disk?=public, dir?=uploads (auto y/m), visibility?=public|private
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(Request $request)
    {
        // Define allowed MIME types and extensions for security
        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain', 'text/csv',
        ];

        $allowedExtensions = [
            'jpg', 'jpeg', 'png', 'gif', 'webp',
            'pdf',
            'doc', 'docx',
            'xls', 'xlsx',
            'txt', 'csv',
        ];

        $this->validate($request, [
            'file' => [
                'required',
                'file',
                'max:10240', // 10MB maximum size
                function ($attribute, $value, $fail) use ($allowedMimes, $allowedExtensions) {
                    // Validate MIME type
                    if (! in_array($value->getMimeType(), $allowedMimes, true)) {
                        $fail(__('The file type is not allowed. Allowed types: images, PDF, Word, Excel, text.'));
                    }
                    // Validate extension
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (! in_array($ext, $allowedExtensions, true)) {
                        $fail(__('The file extension is not allowed.'));
                    }
                },
            ],
            'disk' => ['sometimes', 'string', 'in:public,local,private'], // Limit allowed disks
            'dir' => ['sometimes', 'string', 'max:100'], // Prevent path traversal with length limit
            'visibility' => ['sometimes', 'in:public,private'],
        ]);

        $disk = $this->resolveDisk($request);

        // Sanitize directory path to prevent path traversal attacks
        $baseDir = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', trim((string) $request->input('dir', 'uploads'), '/'));
        $baseDir = str_replace(['..', '~'], '', $baseDir); // Remove potential path traversal
        $dir = $baseDir.'/'.now()->format('Y/m');

        $uploaded = $request->file('file');

        // Use server-detected extension instead of client-provided for security
        $ext = strtolower($uploaded->guessExtension() ?? $uploaded->getClientOriginalExtension());

        // Validate extension one more time
        if (! in_array($ext, $allowedExtensions, true)) {
            return $this->fail(__('Invalid file type detected.'), 422);
        }

        // Generate secure random filename
        $name = Str::random(32).($ext ? ('.'.$ext) : '');

        $visibility = $request->input('visibility', 'private');
        if (! in_array($visibility, ['public', 'private'], true)) {
            $visibility = 'private';
        }

        // Store file with secure settings
        $path = $uploaded->storeAs($dir, $name, [
            'disk' => $disk,
            'visibility' => $visibility,
        ]);

        $url = $this->buildUrl(Storage::disk($disk), $path);

        // Log file upload for audit trail
        Log::info('File uploaded', [
            'user_id' => $request->user()?->id,
            'path' => $path,
            'original_name' => $uploaded->getClientOriginalName(),
            'mime' => $uploaded->getMimeType(),
            'size' => $uploaded->getSize(),
            'visibility' => $visibility,
        ]);

        return $this->ok([
            'disk' => $disk,
            'path' => $path,
            'url' => $url,
            'mime' => $uploaded->getMimeType(), // Use server-detected MIME type
            'size' => $uploaded->getSize(),
            'original_name' => $uploaded->getClientOriginalName(),
            'visibility' => $visibility,
        ], __('File uploaded successfully'));
    }

    /**
     * Validate and sanitize requested disk.
     */
    protected function resolveDisk(Request $request): string
    {
        $disk = (string) $request->input('disk', config('filesystems.default', 'public'));

        if (! in_array($disk, ['public', 'local', 'private'], true)) {
            throw new AuthorizationException(__('Invalid storage disk requested.'));
        }

        return $disk;
    }

    /**
     * Normalize file paths to prevent traversal or unsafe characters.
     */
    protected function normalizePath(string $fileId): string
    {
        $decoded = urldecode($fileId);
        $clean = trim(str_replace('\\', '/', $decoded), '/');

        if ($clean === '' || str_contains($clean, '..')) {
            throw new AuthorizationException(__('Invalid file path.'));
        }

        if (! preg_match('/^[A-Za-z0-9_.\-\/]+$/', $clean)) {
            throw new AuthorizationException(__('Invalid file path.'));
        }

        return $clean;
    }

    protected function buildUrl($storage, string $path): ?string
    {
        try {
            return $storage->url($path);
        } catch (\Throwable) {
            return null;
        }
    }
}
