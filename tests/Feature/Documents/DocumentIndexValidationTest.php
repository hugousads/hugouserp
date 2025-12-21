<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Livewire\Documents\Index;
use App\Models\Branch;
use App\Models\Document;
use App\Models\DocumentShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentIndexValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::define('documents.view', fn () => true);
        Gate::define('documents.delete', fn () => true);
    }

    public function test_sort_field_is_normalized_to_allowlist(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);

        Document::forceCreate([
            'title' => 'Alpha',
            'code' => 'DOC-ALPHA',
            'file_name' => 'alpha.pdf',
            'file_path' => 'documents/alpha.pdf',
            'file_size' => 120,
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'status' => 'published',
            'branch_id' => $branch->id,
            'uploaded_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->set('sortField', 'title desc, (select sleep(1))--')
            ->set('sortDirection', 'sideways')
            ->call('render')
            ->assertSet('sortField', 'created_at')
            ->assertSet('sortDirection', 'desc')
            ->assertViewHas('documents', fn ($documents) => $documents->count() === 1);
    }

    public function test_search_input_is_trimmed_and_wildcards_removed(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);

        Document::forceCreate([
            'title' => 'Safe Query',
            'code' => 'DOC-QUERY',
            'file_name' => 'query.pdf',
            'file_path' => 'documents/query.pdf',
            'file_size' => 220,
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'status' => 'published',
            'branch_id' => $branch->id,
            'uploaded_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->set('search', '   %%%Safe Query%%%   ')
            ->call('render')
            ->assertSet('search', 'Safe Query')
            ->assertViewHas('documents', fn ($documents) => $documents->count() === 1);
    }

    public function test_shared_user_with_full_permission_can_delete(): void
    {
        config(['filesystems.document_disk' => 'local']);
        Storage::fake('local');

        $branch = Branch::factory()->create();
        $owner = User::factory()->create(['branch_id' => $branch->id]);
        $sharedUser = User::factory()->create(['branch_id' => $branch->id]);

        $document = Document::forceCreate([
            'title' => 'Shared Doc',
            'code' => 'DOC-SHARED',
            'file_name' => 'shared.pdf',
            'file_path' => 'documents/shared.pdf',
            'file_size' => 100,
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'status' => 'published',
            'branch_id' => $branch->id,
            'uploaded_by' => $owner->id,
        ]);

        Storage::disk('local')->put($document->file_path, 'shared content');

        DocumentShare::create([
            'document_id' => $document->id,
            'shared_with_user_id' => $sharedUser->id,
            'shared_by' => $owner->id,
            'permission' => 'full',
        ]);

        Livewire::actingAs($sharedUser)
            ->test(Index::class)
            ->call('delete', $document->id)
            ->assertHasNoErrors();

        $this->assertSoftDeleted('documents', ['id' => $document->id]);
    }
}
