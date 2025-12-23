<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MediaDownloadControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::create(['name' => 'media.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'media.view-others', 'guard_name' => 'web']);
        Permission::create(['name' => 'media.manage-all', 'guard_name' => 'web']);
    }

    public function test_user_can_stream_own_media_from_local_disk(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $user->givePermissionTo(['media.view', 'media.view-others']);

        $path = 'media/test-file.txt';
        Storage::disk('local')->put($path, 'file contents');

        $media = Media::create([
            'name' => 'Test File',
            'original_name' => 'test-file.txt',
            'file_path' => $path,
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size' => 14,
            'disk' => 'local',
            'collection' => 'general',
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('app.media.download', $media));

        $response->assertOk();
        $response->assertSee('file contents');
        $this->assertStringContainsString('text/plain', $response->headers->get('content-type'));
    }

    public function test_user_without_view_others_permission_cannot_access_foreign_media(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $owner->givePermissionTo(['media.view', 'media.view-others']);

        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['media.view']);

        $path = 'media/secret.txt';
        Storage::disk('local')->put($path, 'top secret');

        $media = Media::create([
            'name' => 'Secret',
            'original_name' => 'secret.txt',
            'file_path' => $path,
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size' => 10,
            'disk' => 'local',
            'collection' => 'general',
            'user_id' => $owner->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('app.media.download', $media))
            ->assertForbidden();
    }

    public function test_user_cannot_download_media_from_other_branch_without_manage_all(): void
    {
        Storage::fake('local');

        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        $owner = User::factory()->for($branchB)->create();
        $owner->givePermissionTo(['media.view', 'media.view-others']);

        $viewer = User::factory()->for($branchA)->create();
        $viewer->givePermissionTo(['media.view', 'media.view-others']);

        $path = 'media/branch-secret.txt';
        Storage::disk('local')->put($path, 'branch secret');

        $media = Media::create([
            'name' => 'Branch Secret',
            'original_name' => 'branch-secret.txt',
            'file_path' => $path,
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size' => 13,
            'disk' => 'local',
            'collection' => 'general',
            'user_id' => $owner->id,
            'branch_id' => $branchB->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('app.media.download', $media))
            ->assertForbidden();
    }

    public function test_user_with_manage_all_can_download_across_branches(): void
    {
        Storage::fake('local');

        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        $owner = User::factory()->for($branchB)->create();
        $owner->givePermissionTo(['media.view', 'media.view-others']);

        $viewer = User::factory()->for($branchA)->create();
        $viewer->givePermissionTo(['media.view', 'media.manage-all']);

        $path = 'media/branch-shared.txt';
        Storage::disk('local')->put($path, 'shared file');

        $media = Media::create([
            'name' => 'Branch Shared',
            'original_name' => 'branch-shared.txt',
            'file_path' => $path,
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size' => 11,
            'disk' => 'local',
            'collection' => 'general',
            'user_id' => $owner->id,
            'branch_id' => $branchB->id,
        ]);

        $response = $this->actingAs($viewer)->get(route('app.media.download', $media));

        $response->assertOk();
        $response->assertSee('shared file');
    }
}
