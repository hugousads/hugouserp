<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Livewire\Documents\Form;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentUploadValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_html_upload_is_rejected_and_not_stored(): void
    {
        Storage::fake('local');
        config(['filesystems.document_disk' => 'local']);
        Gate::define('documents.create', fn () => true);

        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);

        Livewire::actingAs($user)
            ->test(Form::class)
            ->set('title', 'Malicious Payload')
            ->set('description', 'Test')
            ->set('file', UploadedFile::fake()->create('payload.html', 10, 'text/html'))
            ->call('save')
            ->assertHasErrors(['file' => 'mimes']);

        $this->assertDatabaseCount('documents', 0);
        Storage::disk('local')->assertDirectoryEmpty('documents');
    }
}
