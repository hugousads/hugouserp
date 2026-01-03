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

    public function test_user_without_session_cannot_download(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get('/download/export');

        $response->assertStatus(404);
    }

    public function test_valid_user_can_download_owned_export(): void
    {
        $user = User::factory()->create();

        Storage::disk('local')->put('exports/valid.csv', 'data');
        $path = Storage::disk('local')->path('exports/valid.csv');

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

    public function test_user_cannot_download_another_users_export(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Storage::disk('local')->put('exports/valid.csv', 'data');
        $path = Storage::disk('local')->path('exports/valid.csv');

        $response = $this->actingAs($user)
            ->withSession([
                'export_file' => [
                    'path' => $path,
                    'name' => 'valid.csv',
                    'time' => now()->timestamp,
                    'user_id' => $otherUser->id, // Different user's export
                ],
            ])
            ->get('/download/export');

        $response->assertStatus(403);
    }

    public function test_rejects_path_traversal_attempts(): void
    {
        $user = User::factory()->create();

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

    public function test_rejects_expired_exports(): void
    {
        $user = User::factory()->create();

        Storage::disk('local')->put('exports/old.csv', 'data');
        $path = Storage::disk('local')->path('exports/old.csv');

        $response = $this->actingAs($user)
            ->withSession([
                'export_file' => [
                    'path' => $path,
                    'name' => 'old.csv',
                    'time' => now()->subMinutes(6)->timestamp, // 6 minutes old (expired after 5 minutes)
                    'user_id' => $user->id,
                ],
            ])
            ->get('/download/export');

        $response->assertStatus(410); // Gone
    }

    protected function setPermissions(): void
    {
        // No permission setup needed - export permissions are checked at the page level
        // Download route only verifies user owns the export
    }
}
