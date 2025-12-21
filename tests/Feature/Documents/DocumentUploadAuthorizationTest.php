<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Models\Branch;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentUploadAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_rejects_cross_branch_payload(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branchA->id]);

        $this->actingAs($user);

        config(['filesystems.document_disk' => 'documents_test']);
        Storage::fake('documents_test');

        $file = UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf');

        $this->expectException(AuthorizationException::class);

        app(DocumentService::class)->uploadDocument($file, [
            'title' => 'Cross Branch Upload',
            'branch_id' => $branchB->id,
        ]);
    }
}
