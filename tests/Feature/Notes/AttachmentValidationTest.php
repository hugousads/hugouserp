<?php

declare(strict_types=1);

namespace Tests\Feature\Notes;

use App\Livewire\Components\NotesAttachments;
use App\Models\User;
use App\Services\AttachmentAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class AttachmentValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_disallowed_mime_after_storage_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $validator = app('validator')->make([
            'newFiles' => [
                UploadedFile::fake()->create('malicious.txt', 1, 'application/x-msdownload'),
            ],
        ], [
            'newFiles' => 'required|array|min:1',
            'newFiles.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt|mimetypes:image/jpeg,image/png,image/gif,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,text/csv,text/plain',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $validator->validate();
    }
}
