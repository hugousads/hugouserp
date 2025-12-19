<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\MediaLibrary;
use App\Models\Branch;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $branch = Branch::factory()->create();

        return User::factory()->create(['branch_id' => $branch->id]);
    }

    private function defineMediaGates(bool $viewOthers = true): void
    {
        Gate::define('media.view', fn () => true);
        Gate::define('media.upload', fn () => true);
        Gate::define('media.view-others', fn () => $viewOthers);
        Gate::define('media.manage', fn () => true);
        Gate::define('media.delete', fn () => true);
    }

    public function test_upload_rejects_disallowed_mime(): void
    {
        Storage::fake('public');
        $this->defineMediaGates();
        $user = $this->makeUser();

        Livewire::actingAs($user)
            ->test(MediaLibrary::class)
            ->set('files', [UploadedFile::fake()->create('payload.php', 10, 'text/x-php')])
            ->assertHasErrors(['files.0' => 'mimes']);

        $this->assertSame(0, Media::count());
        Storage::disk('public')->assertDirectoryEmpty('media');
    }

    public function test_search_does_not_leak_other_users_media(): void
    {
        $this->defineMediaGates(false);
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $otherUser = User::factory()->create(['branch_id' => $branch->id]);

        Media::create([
            'name' => 'My Notes',
            'original_name' => 'notes.pdf',
            'file_path' => 'media/notes.pdf',
            'thumbnail_path' => null,
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 1024,
            'optimized_size' => null,
            'width' => null,
            'height' => null,
            'disk' => 'public',
            'collection' => 'general',
            'user_id' => $user->id,
            'branch_id' => $branch->id,
        ]);

        Media::create([
            'name' => 'Quarterly Report',
            'original_name' => 'report.pdf',
            'file_path' => 'media/report.pdf',
            'thumbnail_path' => null,
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 2048,
            'optimized_size' => null,
            'width' => null,
            'height' => null,
            'disk' => 'public',
            'collection' => 'general',
            'user_id' => $otherUser->id,
            'branch_id' => $branch->id,
        ]);

        Livewire::actingAs($user)
            ->test(MediaLibrary::class)
            ->set('filterOwner', 'mine')
            ->set('search', 'report')
            ->assertViewHas('media', fn ($media) => $media->count() === 0);
    }
}
