<?php

declare(strict_types=1);

namespace Tests\Feature\Files;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UploadControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['files.upload', 'files.view', 'files.delete'] as $permission) {
            Permission::findOrCreate($permission);
        }
    }

    public function test_it_uploads_file_with_private_visibility_and_returns_metadata(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $user->givePermissionTo('files.upload');

        $response = $this->actingAs($user, 'web')->postJson('/api/v1/files/upload', [
            'file' => UploadedFile::fake()->create('example.pdf', 50, 'application/pdf'),
        ]);

        $response->assertOk();

        $path = $response->json('data.path');

        Storage::disk('public')->assertExists($path);
        $this->assertSame('private', Storage::disk('public')->getVisibility($path));
        $this->assertEquals('application/pdf', $response->json('data.mime'));
        $this->assertEquals('private', $response->json('data.visibility'));
    }

    public function test_it_rejects_svg_uploads(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $user->givePermissionTo('files.upload');

        $response = $this->actingAs($user, 'web')->postJson('/api/v1/files/upload', [
            'file' => UploadedFile::fake()->create('malicious.svg', 10, 'image/svg+xml'),
        ]);

        $response->assertStatus(422);
    }

    public function test_it_returns_file_metadata(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $user->givePermissionTo(['files.view']);

        $path = 'uploads/2024/01/example.txt';
        Storage::disk('public')->put($path, 'example');

        $response = $this->actingAs($user, 'web')->getJson('/api/v1/files/'.$path.'/meta');

        $response->assertOk();
        $response->assertJsonPath('data.path', $path);
        $this->assertSame('public', $response->json('data.disk'));
        $this->assertEquals(strlen('example'), $response->json('data.size'));
    }

    public function test_it_downloads_file_safely(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $user->givePermissionTo(['files.view']);

        $path = 'uploads/2024/01/example.txt';
        Storage::disk('public')->put($path, 'example');

        $response = $this->actingAs($user, 'web')->get('/api/v1/files/'.$path);

        $response->assertOk();
        $this->assertEquals('example', $response->getContent());
    }

    public function test_it_deletes_file(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $user->givePermissionTo(['files.delete']);

        $path = 'uploads/2024/01/example.txt';
        Storage::disk('public')->put($path, 'example');

        $response = $this->actingAs($user, 'web')->deleteJson('/api/v1/files/'.$path);

        $response->assertOk();
        Storage::disk('public')->assertMissing($path);
    }
}
