<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\DocumentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends Controller
{
    public function __construct(protected DocumentService $documentService)
    {
    }

    public function __invoke(Document $document): StreamedResponse
    {
        // Prevent cross-branch document download (IDOR protection)
        $user = Auth::user();
        if ($user && $user->branch_id && $document->branch_id && $user->branch_id !== $document->branch_id) {
            abort(403, 'You cannot download documents from other branches.');
        }

        $inline = request()->boolean('inline');
        
        $disk = $this->documentService->documentsDisk();
        $resolvedDisk = Storage::disk($disk)->exists($document->file_path)
            ? $disk
            : 'public';

        // Verify file exists on the resolved disk
        abort_unless(Storage::disk($resolvedDisk)->exists($document->file_path), 404, 'File not found');
        
        // Validate access and log download (throws 403 if unauthorized)
        $this->documentService->downloadDocument($document, Auth::user());

        $headers = ['Content-Type' => $document->mime_type];

        if ($inline) {
            return Storage::disk($resolvedDisk)->response(
                $document->file_path,
                $document->file_name,
                $headers
            );
        }

        return Storage::disk($resolvedDisk)->download($document->file_path, $document->file_name, $headers);
    }
}
