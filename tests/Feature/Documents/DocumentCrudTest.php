<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Models\Branch;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create(['name' => 'Test Branch', 'code' => 'TB001']);
        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
    }

    protected function createDocument(array $overrides = []): Document
    {
        return Document::forceCreate(array_merge([
            'title' => 'Test Document',
            'code' => 'DOC-' . Str::random(6),
            'file_name' => 'test.pdf',
            'file_path' => 'documents/test.pdf',
            'file_size' => 1024,
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'status' => 'published',
            'branch_id' => $this->branch->id,
            'uploaded_by' => $this->user->id,
        ], $overrides));
    }

    public function test_can_create_document(): void
    {
        $document = $this->createDocument([
            'description' => 'Test description',
        ]);

        $this->assertDatabaseHas('documents', ['title' => 'Test Document']);
    }

    public function test_can_read_document(): void
    {
        $document = $this->createDocument();

        $found = Document::find($document->id);
        $this->assertNotNull($found);
    }

    public function test_can_update_document(): void
    {
        $document = $this->createDocument();

        $document->update(['title' => 'Updated Document']);
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'title' => 'Updated Document']);
    }

    public function test_can_delete_document(): void
    {
        $document = $this->createDocument();

        $document->delete();
        $this->assertSoftDeleted('documents', ['id' => $document->id]);
    }
}
