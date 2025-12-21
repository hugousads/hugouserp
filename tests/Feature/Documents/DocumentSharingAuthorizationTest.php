<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Livewire\Documents\Show;
use App\Models\Branch;
use App\Models\Document;
use App\Models\DocumentShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentSharingAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function createDocument(User $owner, Branch $branch): Document
    {
        return Document::forceCreate([
            'code' => 'DOC-' . uniqid(),
            'title' => 'Shared Doc',
            'description' => 'Test document',
            'file_name' => 'doc.pdf',
            'file_path' => 'documents/doc.pdf',
            'file_size' => 1024,
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'folder' => null,
            'category' => null,
            'is_public' => false,
            'uploaded_by' => $owner->id,
            'branch_id' => $branch->id,
            'metadata' => null,
            'status' => 'draft',
            'access_level' => 'private',
            'version' => 1,
        ]);
    }

    private function defineShareGates(bool $manage = false): void
    {
        Gate::define('documents.view', fn () => true);
        Gate::define('documents.share', fn () => true);
        Gate::define('documents.manage', fn () => $manage);
    }

    public function test_non_owner_cannot_share_document(): void
    {
        $branch = Branch::factory()->create();
        $owner = User::factory()->create(['branch_id' => $branch->id]);
        $viewer = User::factory()->create(['branch_id' => $branch->id]);
        $target = User::factory()->create(['branch_id' => $branch->id]);
        $document = $this->createDocument($owner, $branch);

        DocumentShare::create([
            'document_id' => $document->id,
            'user_id' => $viewer->id,
            'shared_by' => $owner->id,
            'permission' => 'view',
            'expires_at' => null,
            'access_count' => 0,
        ]);

        $this->defineShareGates();

        Livewire::actingAs($viewer)
            ->test(Show::class, ['document' => $document])
            ->set('shareUserId', $target->id)
            ->set('sharePermission', 'view')
            ->call('shareDocument')
            ->assertForbidden();

        $this->assertDatabaseCount('document_shares', 1);
    }

    public function test_non_owner_cannot_unshare_document(): void
    {
        $branch = Branch::factory()->create();
        $owner = User::factory()->create(['branch_id' => $branch->id]);
        $viewer = User::factory()->create(['branch_id' => $branch->id]);
        $sharedUser = User::factory()->create(['branch_id' => $branch->id]);
        $document = $this->createDocument($owner, $branch);

        DocumentShare::create([
            'document_id' => $document->id,
            'user_id' => $viewer->id,
            'shared_by' => $owner->id,
            'permission' => 'view',
            'expires_at' => null,
            'access_count' => 0,
        ]);

        DocumentShare::create([
            'document_id' => $document->id,
            'user_id' => $sharedUser->id,
            'shared_by' => $owner->id,
            'permission' => 'view',
            'expires_at' => null,
            'access_count' => 0,
        ]);

        $this->defineShareGates();

        Livewire::actingAs($viewer)
            ->test(Show::class, ['document' => $document])
            ->call('unshare', $sharedUser->id)
            ->assertForbidden();

        $this->assertDatabaseCount('document_shares', 2);
    }
}
