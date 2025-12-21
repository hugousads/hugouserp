<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Livewire\Documents\Index;
use App\Models\Branch;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DocumentDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_delete_document_from_other_branch(): void
    {
        Gate::define('documents.view', fn () => true);
        Gate::define('documents.delete', fn () => true);

        Storage::fake('local');
        Storage::fake('public');

        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branchA->id]);
        $uploader = User::factory()->create(['branch_id' => $branchB->id]);

        $document = Document::forceCreate([
            'code' => 'DOC-1',
            'title' => 'Branch B Doc',
            'file_name' => 'doc.pdf',
            'file_path' => 'documents/doc.pdf',
            'file_size' => 100,
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'folder' => null,
            'category' => null,
            'status' => 'draft',
            'access_level' => 'private',
            'version' => 1,
            'version_number' => 1,
            'is_public' => false,
            'uploaded_by' => $uploader->id,
            'branch_id' => $branchB->id,
        ]);

        $this->actingAs($user);

        try {
            Livewire::test(Index::class)->call('delete', $document->id);
            $this->fail('Expected denial when deleting a document from another branch.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'deleted_at' => null,
        ]);
    }
}
