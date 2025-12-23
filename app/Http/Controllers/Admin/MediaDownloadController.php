<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class MediaDownloadController extends Controller
{
    public function __invoke(Media $media, Request $request): Response
    {
        $user = $request->user();
        $canManageAll = $user?->can('media.manage-all') ?? false;

        abort_unless($user?->can('media.view'), 403, __('You do not have permission to view media files.'));

        $canBypassBranch = ! $user?->branch_id || $canManageAll;

        if (
            $user?->branch_id
            && $media->branch_id
            && $user->branch_id !== $media->branch_id
            && ! $canBypassBranch
        ) {
            abort(403, __('You do not have permission to access this file.'));
        }

        if (! $canManageAll && ! $user->can('media.view-others') && $media->user_id !== $user->id) {
            abort(403, __('You do not have permission to access this file.'));
        }

        $serveThumbnail = $request->boolean('thumbnail');
        $path = $serveThumbnail && $media->thumbnail_path ? $media->thumbnail_path : $media->file_path;

        $disk = Storage::disk($media->disk);

        abort_unless($disk->exists($path), 404, __('File not found.'));

        $filename = $serveThumbnail
            ? basename($path)
            : ($media->original_name ?: $media->name);

        $headers = [
            'Content-Type' => $media->mime_type ?? 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ];

        $content = $disk->get($path);

        return response($content, 200, $headers);
    }
}
