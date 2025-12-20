<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\DocumentService;
use Illuminate\Support\Facades\Auth;
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

        return $this->documentService->downloadDocument($document, Auth::user(), $inline);
    }
}
