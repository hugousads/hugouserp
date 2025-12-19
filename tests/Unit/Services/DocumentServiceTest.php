<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Branch;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DocumentService $service;
    protected Branch $branch;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DocumentService::class);

        $this->branch = Branch::create([
            'name' => 'Test Branch',
            'code' => 'TB001',
        ]);

        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
        ]);
    }

    protected function createDocument(array $overrides = []): Document
    {
        return Document::create(array_merge([
            'title' => 'Test Document',
            'code' => 'DOC-' . uniqid(),
            'file_name' => 'test.pdf',
            'file_path' => 'documents/test.pdf',
            'file_size' => 1024,
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'status' => 'published',
            'is_public' => false,
            'branch_id' => $this->branch->id,
            'uploaded_by' => $this->user->id,
        ], $overrides));
    }

    public function test_can_get_statistics(): void
    {
        $this->createDocument();
        $this->createDocument();

        $stats = $this->service->getStatistics($this->branch->id);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_documents', $stats);
    }

    public function test_validates_public_document_access(): void
    {
        $document = $this->createDocument([
            'is_public' => true,
        ]);

        // Public documents should be accessible
        $hasAccess = $document->canBeAccessedBy($this->user);

        $this->assertTrue($hasAccess);
    }

    public function test_validates_owner_document_access(): void
    {
        $document = $this->createDocument([
            'is_public' => false,
            'uploaded_by' => $this->user->id,
        ]);

        // Owner should be able to access their document
        $hasAccess = $document->canBeAccessedBy($this->user);

        $this->assertTrue($hasAccess);
    }

    public function test_document_file_size_formatted(): void
    {
        $document = $this->createDocument([
            'file_size' => 1536000, // ~1.5 MB
        ]);

        $formatted = $document->getFileSizeFormatted();

        $this->assertStringContainsString('MB', $formatted);
    }

    public function test_upload_document_rejects_disallowed_mime(): void
    {
        Storage::fake('local');
        config(['filesystems.document_disk' => 'local']);
        $this->actingAs($this->user);

        try {
            $this->service->uploadDocument(
                UploadedFile::fake()->create('payload.html', 10, 'text/html'),
                [
                    'title' => 'Unsafe',
                    'description' => null,
                    'folder' => null,
                    'category' => null,
                    'is_public' => false,
                    'branch_id' => $this->branch->id,
                ]
            );
            $this->fail('Expected validation exception was not thrown.');
        } catch (ValidationException $e) {
            $this->assertDatabaseCount('documents', 0);
            Storage::disk('local')->assertDirectoryEmpty('documents');
        }
    }
}
