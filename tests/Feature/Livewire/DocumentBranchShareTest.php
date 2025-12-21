<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Documents\Show;
use App\Models\Branch;
use App\Models\Document;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DocumentBranchShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_shareable_users_are_limited_to_document_branch(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $uploader = User::factory()->create(['branch_id' => $branchA->id]);
        $sameBranchUser = User::factory()->create(['branch_id' => $branchA->id]);
        $otherBranchUser = User::factory()->create(['branch_id' => $branchB->id]);
        $this->giveDocumentPermissions($uploader);

        $document = Document::forceCreate([
            'code' => 'DOC-001',
            'title' => 'Shared Doc',
            'file_name' => 'doc.pdf',
            'file_path' => 'documents/doc.pdf',
            'file_size' => 100,
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'uploaded_by' => $uploader->id,
            'branch_id' => $branchA->id,
        ]);

        $this->actingAs($uploader);

        Livewire::test(Show::class, ['document' => $document])
            ->assertViewHas('users', function ($users) use ($sameBranchUser, $otherBranchUser) {
                return $users->contains('id', $sameBranchUser->id)
                    && ! $users->contains('id', $otherBranchUser->id);
            });
    }

    public function test_sharing_to_other_branch_is_blocked(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $uploader = User::factory()->create(['branch_id' => $branchA->id]);
        $otherBranchUser = User::factory()->create(['branch_id' => $branchB->id]);
        $this->giveDocumentPermissions($uploader);

        $document = Document::forceCreate([
            'code' => 'DOC-002',
            'title' => 'Secure Doc',
            'file_name' => 'secure.pdf',
            'file_path' => 'documents/secure.pdf',
            'file_size' => 100,
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'uploaded_by' => $uploader->id,
            'branch_id' => $branchA->id,
        ]);

        $this->actingAs($uploader);

        $this->expectException(AuthorizationException::class);

        Livewire::test(Show::class, ['document' => $document])
            ->set('shareUserId', $otherBranchUser->id)
            ->call('shareDocument');
    }

    protected function giveDocumentPermissions(User $user): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('documents.view', 'web');
        Permission::findOrCreate('documents.share', 'web');

        $user->givePermissionTo(['documents.view', 'documents.share']);
    }
}
