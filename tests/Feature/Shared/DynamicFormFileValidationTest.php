<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Livewire\Shared\DynamicForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DynamicFormFileValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_file_uploads_are_validated_for_mime_types(): void
    {
        Storage::fake('local');

        Livewire::test(DynamicForm::class, [
            'schema' => [
                ['name' => 'attachment', 'type' => 'file'],
            ],
        ])
            ->set('data.attachment', UploadedFile::fake()->create('payload.php', 5, 'application/x-php'))
            ->call('submit')
            ->assertHasErrors(['data.attachment' => 'mimes']);
    }

    public function test_files_are_stored_on_private_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $component = Livewire::test(DynamicForm::class, [
            'schema' => [
                ['name' => 'attachment', 'type' => 'file'],
            ],
        ])
            ->set('data.attachment', UploadedFile::fake()->create('manual.pdf', 5, 'application/pdf'))
            ->call('submit')
            ->assertHasNoErrors();

        $savedPath = $component->get('data')['attachment'];

        Storage::disk('local')->assertExists($savedPath);
        Storage::disk('public')->assertMissing($savedPath);
    }
}
