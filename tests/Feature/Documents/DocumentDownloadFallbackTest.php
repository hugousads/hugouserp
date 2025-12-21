<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Models\Branch;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DocumentDownloadFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_private_file_returns_service_unavailable_instead_of_public_fallback(): void
    {
        config(['filesystems.document_disk' => 'local']);
        Storage::fake('local');
        Storage::fake('public');

        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);

        Permission::findOrCreate('documents.download', 'web');
        $user->givePermissionTo('documents.download');

        $document = Document::forceCreate([
            'title' => 'Secure Document',
            'code' => 'DOC-SEC',
            'file_name' => 'secure.pdf',
            'file_path' => 'documents/secure.pdf',
            'file_size' => 100,
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'status' => 'published',
            'branch_id' => $branch->id,
            'uploaded_by' => $user->id,
            'is_public' => false,
            'access_level' => 'private',
        ]);

        Storage::disk('public')->put($document->file_path, 'public copy');

        $this->actingAs($user);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('File temporarily unavailable');

        app(DocumentService::class)->downloadDocument($document, $user);
    }

    public function test_fallback_disk_is_used_when_primary_is_missing(): void
    {
        config([
            'filesystems.document_disk' => 'primary',
            'filesystems.document_disk_fallback' => 'secondary',
        ]);

        Storage::fake('primary');
        Storage::fake('secondary');
        Log::fake();

        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);

        Permission::findOrCreate('documents.download', 'web');
        $user->givePermissionTo('documents.download');

        $document = Document::forceCreate([
            'title' => 'Fallback Document',
            'code' => 'DOC-FALLBACK',
            'file_name' => 'fallback.pdf',
            'file_path' => 'documents/fallback.pdf',
            'file_size' => 100,
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'status' => 'published',
            'branch_id' => $branch->id,
            'uploaded_by' => $user->id,
            'is_public' => false,
            'access_level' => 'private',
        ]);

        Storage::disk('secondary')->put($document->file_path, 'secondary copy');

        $this->actingAs($user);

        $response = app(DocumentService::class)->downloadDocument($document, $user, inline: true);

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response);
        Log::assertLogged('warning', function ($message, $context) use ($document) {
            return str_contains($message, 'Primary document disk missing file')
                && ($context['path'] ?? null) === $document->file_path;
        });
    }
}
