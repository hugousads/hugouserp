<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Models\Branch;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\UIHelperService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentSchemaAlignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_and_version_operations_use_expected_columns(): void
    {
        Storage::fake('local');
        config(['filesystems.document_disk' => 'local']);

        $user = User::factory()->create();
        $branch = Branch::factory()->create();
        $this->actingAs($user);

        $service = new DocumentService(new UIHelperService());

        $document = $service->uploadDocument(
            UploadedFile::fake()->create('contract.pdf', 10, 'application/pdf'),
            [
                'title' => 'Contract',
                'description' => 'Base document',
                'branch_id' => $branch->id,
                'is_public' => false,
            ]
        );

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'is_public' => false,
            'version' => 1,
            'version_number' => 1,
        ]);

        $version = $service->uploadVersion(
            $document,
            UploadedFile::fake()->create('contract-v2.pdf', 12, 'application/pdf'),
            'Updated pricing'
        );

        $document->refresh();

        $this->assertSame(2, $document->version);
        $this->assertSame(2, $document->version_number);

        $this->assertDatabaseHas('document_versions', [
            'id' => $version->id,
            'file_name' => 'contract-v2.pdf',
            'mime_type' => 'application/pdf',
            'change_notes' => 'Updated pricing',
        ]);
    }

    public function test_document_sharing_relies_on_user_id_column(): void
    {
        Storage::fake('local');
        config(['filesystems.document_disk' => 'local']);

        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        $branch = Branch::factory()->create();

        $this->actingAs($owner);

        $service = new DocumentService(new UIHelperService());
        $document = $service->uploadDocument(
            UploadedFile::fake()->create('handbook.pdf', 6, 'application/pdf'),
            [
                'title' => 'Handbook',
                'branch_id' => $branch->id,
            ]
        );

        $service->shareDocument($document, $recipient->id, 'view');

        $this->assertDatabaseHas('document_shares', [
            'document_id' => $document->id,
            'user_id' => $recipient->id,
        ]);

        $this->assertTrue($document->fresh()->canBeAccessedBy($recipient));

        $service->unshareDocument($document, $recipient->id);

        $this->assertDatabaseMissing('document_shares', [
            'document_id' => $document->id,
            'user_id' => $recipient->id,
        ]);
    }
}
