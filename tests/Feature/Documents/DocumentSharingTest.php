<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Models\Branch;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentSharingTest extends TestCase
{
    use RefreshDatabase;

    public function test_share_and_unshare_use_shared_with_user_id(): void
    {
        $branch = Branch::factory()->create();
        $owner = User::factory()->create([
            'branch_id' => $branch->id,
            'password' => Hash::make('password'),
        ]);
        $recipient = User::factory()->create(['branch_id' => $branch->id]);

        $this->actingAs($owner);

        $document = Document::forceCreate([
            'title' => 'Test Document',
            'code' => Str::uuid()->toString(),
            'file_name' => 'doc.pdf',
            'file_path' => 'documents/doc.pdf',
            'file_size' => 1024,
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'status' => 'private',
            'branch_id' => $branch->id,
            'uploaded_by' => $owner->id,
        ]);

        $service = app(DocumentService::class);
        $service->shareDocument($document, $recipient->id, 'view');

        $this->assertDatabaseHas('document_shares', [
            'document_id' => $document->id,
            'shared_with_user_id' => $recipient->id,
            'shared_by' => $owner->id,
        ]);

        $this->assertTrue($document->fresh()->canBeAccessedBy($recipient));

        $service->unshareDocument($document, $recipient->id);

        $this->assertDatabaseMissing('document_shares', [
            'document_id' => $document->id,
            'shared_with_user_id' => $recipient->id,
        ]);
    }

    public function test_get_file_size_formatted_handles_null_size(): void
    {
        $document = new Document([
            'title' => 'Size Test',
        ]);

        $this->assertSame('0 B', $document->getFileSizeFormatted());
    }
}
