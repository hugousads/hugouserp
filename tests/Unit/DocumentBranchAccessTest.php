<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\Document;
use App\Models\DocumentShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentBranchAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_user_from_other_branch_cannot_access_private_document(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        $owner = User::factory()->create(['branch_id' => $branchA->id]);
        $otherBranchUser = User::factory()->create(['branch_id' => $branchB->id]);

        $document = Document::forceCreate([
            'title' => 'Private Doc',
            'code' => 'DOC-PRIVATE',
            'file_name' => 'private.pdf',
            'file_path' => 'documents/private.pdf',
            'file_size' => 128,
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'status' => 'published',
            'branch_id' => $branchA->id,
            'uploaded_by' => $owner->id,
            'is_public' => false,
            'access_level' => 'private',
        ]);

        DocumentShare::create([
            'document_id' => $document->id,
            'shared_with_user_id' => $otherBranchUser->id,
            'shared_by' => $owner->id,
            'permission' => 'view',
        ]);

        $this->assertFalse($document->canBeAccessedBy($otherBranchUser));
    }

    public function test_public_document_can_be_accessed_across_branches(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        $owner = User::factory()->create(['branch_id' => $branchA->id]);
        $otherBranchUser = User::factory()->create(['branch_id' => $branchB->id]);

        $document = Document::forceCreate([
            'title' => 'Public Doc',
            'code' => 'DOC-PUBLIC',
            'file_name' => 'public.pdf',
            'file_path' => 'documents/public.pdf',
            'file_size' => 256,
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'status' => 'published',
            'branch_id' => $branchA->id,
            'uploaded_by' => $owner->id,
            'is_public' => true,
            'access_level' => 'public',
        ]);

        $this->assertTrue($document->canBeAccessedBy($otherBranchUser));
    }
}
