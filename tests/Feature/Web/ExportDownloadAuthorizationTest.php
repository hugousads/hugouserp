<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExportDownloadAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withExceptionHandling();
        $this->setPermissions();
    }

    public function test_user_without_permission_cannot_download_even_with_session(): void
    {
        $user = User::factory()->create();
        $path = Storage::disk('local')->path('exports/test.txt');
        Storage::disk('local')->put('exports/test.txt', 'secret');

        $response = $this->actingAs($user)
            ->withSession([
                'export_file' => [
                    'path' => $path,
                    'name' => 'test.txt',
                    'time' => now()->timestamp,
                    'user_id' => $user->id,
                ],
            ])
            ->get('/download/export');

        $response->assertStatus(403);
    }

    public function test_valid_user_can_download_owned_export(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('reports.download');

        Storage::disk('local')->put('exports/valid.csv', 'data');
        $path = Storage::disk('local')->path('exports/valid.csv');

        $this->assertTrue($user->can('reports.download'));
        $this->assertFileExists($path);

        $response = $this->actingAs($user)
            ->withSession([
                'export_file' => [
                    'path' => $path,
                    'name' => 'valid.csv',
                    'time' => now()->timestamp,
                    'user_id' => $user->id,
                ],
            ])
            ->get('/download/export');

        $response->assertOk();
        $response->assertHeader('content-disposition');
    }

    public function test_rejects_path_traversal_attempts(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('reports.download');

        $response = $this->actingAs($user)
            ->withSession([
                'export_file' => [
                    'path' => base_path('.env'),
                    'name' => '.env',
                    'time' => now()->timestamp,
                    'user_id' => $user->id,
                ],
            ])
            ->get('/download/export');

        $response->assertStatus(403);
    }

    protected function setPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('reports.download', 'web');
    }
}
