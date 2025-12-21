<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Admin\MediaLibrary;
use App\Models\Branch;
use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MediaLibraryBranchScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('media.view', 'web');
        Permission::findOrCreate('media.delete', 'web');
    }

    public function test_media_listing_is_scoped_to_user_branch(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branchA->id]);
        $user->givePermissionTo('media.view');

        $visibleMedia = Media::create([
            'name' => 'Branch A File',
            'original_name' => 'branch-a.pdf',
            'file_path' => 'media/a.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 1024,
            'disk' => 'public',
            'collection' => 'general',
            'user_id' => $user->id,
            'branch_id' => $branchA->id,
        ]);

        $hiddenMedia = Media::create([
            'name' => 'Branch B File',
            'original_name' => 'branch-b.pdf',
            'file_path' => 'media/b.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 2048,
            'disk' => 'public',
            'collection' => 'general',
            'user_id' => $user->id,
            'branch_id' => $branchB->id,
        ]);

        Livewire::actingAs($user)
            ->test(MediaLibrary::class)
            ->assertSee($visibleMedia->name)
            ->assertDontSee($hiddenMedia->name);
    }

    public function test_delete_respects_branch_scope_without_cross_branch_permission(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branchA->id]);
        $user->givePermissionTo(['media.view', 'media.delete']);

        $otherBranchMedia = Media::create([
            'name' => 'Branch B File',
            'original_name' => 'branch-b.pdf',
            'file_path' => 'media/b.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 2048,
            'disk' => 'public',
            'collection' => 'general',
            'user_id' => $user->id,
            'branch_id' => $branchB->id,
        ]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($user)
            ->test(MediaLibrary::class)
            ->call('delete', $otherBranchMedia->id);
    }
}
