<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Livewire\Documents\Versions;
use App\Models\Branch;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentVersionUploadTest extends TestCase
{
    use RefreshDatabase;

    private function makeDocument(User $user): Document
    {
        $branch = Branch::factory()->create();

        return Document::create([
            'code' => 'DOC-' . uniqid(),
            'title' => 'Spec',
            'description' => 'Test document',
            'file_name' => 'seed.pdf',
            'file_path' => 'documents/seed.pdf',
            'file_size' => 1024,
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'folder' => null,
            'category' => null,
            'is_public' => false,
            'uploaded_by' => $user->id,
            'branch_id' => $branch->id,
            'metadata' => null,
        ]);
    }

    public function test_disallowed_mime_is_rejected(): void
    {
        Storage::fake('local');
        config(['filesystems.document_disk' => 'local']);
        Gate::define('documents.versions.manage', fn () => true);

        $user = User::factory()->create();
        $document = $this->makeDocument($user);

        Livewire::actingAs($user)
            ->test(Versions::class, ['document' => $document])
            ->set('file', UploadedFile::fake()->create('payload.html', 10, 'text/html'))
            ->call('uploadVersion')
            ->assertHasErrors(['file' => 'mimes']);
    }

    public function test_allowed_mime_is_stored_on_private_disk(): void
    {
        Storage::fake('local');
        config(['filesystems.document_disk' => 'local']);
        Gate::define('documents.versions.manage', fn () => true);

        $user = User::factory()->create();
        $document = $this->makeDocument($user);

        Livewire::actingAs($user)
            ->test(Versions::class, ['document' => $document])
            ->set('file', UploadedFile::fake()->create('contract.pdf', 10, 'application/pdf'))
            ->call('uploadVersion')
            ->assertHasNoErrors();

        Storage::disk('local')->assertExists('documents');
    }
}
