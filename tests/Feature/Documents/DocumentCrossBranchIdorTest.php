<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Models\Branch;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DocumentCrossBranchIdorTest extends TestCase
{
    use RefreshDatabase;

    protected User $userBranchA;
    protected User $userBranchB;
    protected User $userBranchAWithoutDownload;
    protected Branch $branchA;
    protected Branch $branchB;
    protected Document $documentBranchA;
    protected Document $documentBranchB;

    protected function setUp(): void
    {
        parent::setUp();

        // Create two branches
        $this->branchA = Branch::create(['name' => 'Branch A', 'code' => 'BRA']);
        $this->branchB = Branch::create(['name' => 'Branch B', 'code' => 'BRB']);

        // Create permissions
        Permission::create(['name' => 'documents.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'documents.manage', 'guard_name' => 'web']);
        Permission::create(['name' => 'documents.edit', 'guard_name' => 'web']);
        Permission::create(['name' => 'documents.download', 'guard_name' => 'web']);

        // Create role with document permissions
        $role = Role::create(['name' => 'Document Manager', 'guard_name' => 'web']);
        $role->givePermissionTo(['documents.view', 'documents.manage', 'documents.edit', 'documents.download']);

        // Create users in different branches
        $this->userBranchA = User::factory()->create(['branch_id' => $this->branchA->id]);
        $this->userBranchA->assignRole($role);

        $this->userBranchB = User::factory()->create(['branch_id' => $this->branchB->id]);
        $this->userBranchB->assignRole($role);

        $this->userBranchAWithoutDownload = User::factory()->create(['branch_id' => $this->branchA->id]);
        $this->userBranchAWithoutDownload->givePermissionTo(['documents.view', 'documents.manage', 'documents.edit']);

        // Create documents in each branch
        $this->documentBranchA = Document::create([
            'title' => 'Document in Branch A',
            'code' => 'DOC-A-' . Str::random(6),
            'file_name' => 'test-a.pdf',
            'file_path' => 'documents/test-a.pdf',
            'file_size' => 1024,
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'status' => 'published',
            'branch_id' => $this->branchA->id,
            'uploaded_by' => $this->userBranchA->id,
        ]);

        $this->documentBranchB = Document::create([
            'title' => 'Document in Branch B',
            'code' => 'DOC-B-' . Str::random(6),
            'file_name' => 'test-b.pdf',
            'file_path' => 'documents/test-b.pdf',
            'file_size' => 1024,
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'status' => 'published',
            'branch_id' => $this->branchB->id,
            'uploaded_by' => $this->userBranchB->id,
        ]);
    }

    public function test_user_cannot_view_document_from_other_branch(): void
    {
        $this->actingAs($this->userBranchA);

        // Try to view document from Branch B - should get 403
        $this->withoutExceptionHandling();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('You cannot access documents from other branches.');

        $this->get(route('app.documents.show', $this->documentBranchB->id));
    }

    public function test_user_cannot_edit_document_from_other_branch(): void
    {
        $this->actingAs($this->userBranchA);

        // Try to edit document from Branch B - should get 403
        $this->withoutExceptionHandling();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('You cannot access documents from other branches.');

        $this->get(route('app.documents.edit', $this->documentBranchB->id));
    }

    public function test_user_cannot_download_document_from_other_branch(): void
    {
        $this->actingAs($this->userBranchA);

        // Try to download document from Branch B - should get 403
        $this->withoutExceptionHandling();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('You cannot download documents from other branches.');

        $this->get(route('app.documents.download', $this->documentBranchB->id));
    }

    public function test_user_can_view_own_branch_document(): void
    {
        $this->actingAs($this->userBranchA);

        // View document from own Branch A
        $response = $this->get(route('app.documents.show', $this->documentBranchA->id));

        $response->assertStatus(200);
    }

    public function test_user_can_edit_own_branch_document(): void
    {
        $this->actingAs($this->userBranchA);

        // Edit document from own Branch A
        $response = $this->get(route('app.documents.edit', $this->documentBranchA->id));

        $response->assertStatus(200);
    }

    public function test_document_service_prevents_cross_branch_update(): void
    {
        $this->actingAs($this->userBranchA);

        $documentService = app(\App\Services\DocumentService::class);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->expectExceptionMessage('You cannot update documents from other branches.');

        // Try to update document from Branch B
        $documentService->updateDocument($this->documentBranchB, [
            'title' => 'Hacked Title',
        ]);
    }

    public function test_inline_preview_respects_permissions_and_serves_inline(): void
    {
        Storage::disk('local')->put($this->documentBranchA->file_path, 'pdf content');

        $this->actingAs($this->userBranchA);

        $response = $this->get(route('app.documents.download', [
            'document' => $this->documentBranchA->id,
            'inline' => true,
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('inline;', (string) $response->headers->get('content-disposition'));
    }

    public function test_user_without_download_permission_cannot_download_document(): void
    {
        $this->actingAs($this->userBranchAWithoutDownload);

        $this->withoutExceptionHandling();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('You do not have permission to download this document');

        $this->get(route('app.documents.download', $this->documentBranchA->id));
    }
}
