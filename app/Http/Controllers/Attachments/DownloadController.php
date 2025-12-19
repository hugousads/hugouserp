<?php

declare(strict_types=1);

namespace App\Http\Controllers\Attachments;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Services\AttachmentAuthorizationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends Controller
{
    public function __construct(private AttachmentAuthorizationService $authorizer)
    {
    }

    public function __invoke(Attachment $attachment): StreamedResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $this->authorizer->authorizeForAttachment($user, $attachment);

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        $disposition = $attachment->isImage() || $attachment->isPdf() ? 'inline' : 'attachment';

        return Storage::disk($attachment->disk)->response(
            $attachment->path,
            $attachment->original_filename,
            [
                'Content-Type' => $attachment->mime_type,
                'Content-Disposition' => $disposition.'; filename="'.addslashes($attachment->original_filename).'"',
            ]
        );
    }
}
