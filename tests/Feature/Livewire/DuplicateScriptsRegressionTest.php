<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Regression test to ensure Livewire and Alpine scripts are loaded exactly once.
 * 
 * This test prevents the bug where multiple instances of Livewire/Alpine
 * are initialized, causing console warnings and broken Livewire behavior.
 * 
 * Root cause: When Livewire's `inject_assets` config is set to true,
 * Livewire v3 automatically injects both Livewire scripts AND Alpine.js.
 * Combined with manual @livewireScripts/@livewireStyles in layouts,
 * this causes duplicate initialization and console errors:
 * - "Detected multiple instances of Livewire running"
 * - "Detected multiple instances of Alpine running"
 */
class DuplicateScriptsRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        
        // Create dashboard permission
        Permission::findOrCreate('dashboard.view', 'web');
    }

    /**
     * Test the Livewire config has inject_assets disabled.
     * 
     * This is the critical guard that prevents duplicate scripts.
     * 
     * When inject_assets is true in Livewire v3:
     * - Livewire automatically injects its scripts
     * - Alpine.js is also injected (bundled with Livewire v3)
     * 
     * Combined with manual @livewireScripts/@livewireStyles in layouts,
     * this causes the "Detected multiple instances" console errors.
     */
    public function test_livewire_inject_assets_is_disabled(): void
    {
        $this->assertFalse(
            config('livewire.inject_assets'),
            'Livewire inject_assets should be false. When true, Livewire auto-injects scripts which duplicates the manual @livewireScripts in layouts.'
        );
    }

    /**
     * Test that Livewire scripts/styles directives aren't duplicated in layouts.
     * 
     * When using @livewireScripts/@livewireStyles in layouts, they should only appear once.
     */
    public function test_livewire_directives_not_duplicated(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->givePermissionTo('dashboard.view');

        $response = $this->actingAs($user)->get('/dashboard');
        $content = $response->getContent();

        // Check for duplicate Livewire script tags with src attribute
        // This pattern matches actual script tag loads, not inline mentions
        $livewireScriptTags = preg_match_all('/<script[^>]+livewire[^>]+src=[^>]+>/', $content);
        
        $this->assertLessThanOrEqual(1, $livewireScriptTags, 
            'Livewire script tag should appear at most once in the page.');
    }

    /**
     * Test that the app layout has livewire directives.
     * 
     * Ensures that @livewireStyles and @livewireScripts are present in the layout
     * (since inject_assets is disabled, we need manual inclusion).
     */
    public function test_app_layout_includes_livewire_directives(): void
    {
        $layoutPath = resource_path('views/layouts/app.blade.php');
        $layoutContent = file_get_contents($layoutPath);
        
        $this->assertStringContainsString('@livewireStyles', $layoutContent,
            'App layout should include @livewireStyles directive.');
        
        $this->assertStringContainsString('@livewireScripts', $layoutContent,
            'App layout should include @livewireScripts directive.');
    }

    /**
     * Test that the guest layout has livewire directives.
     */
    public function test_guest_layout_includes_livewire_directives(): void
    {
        $layoutPath = resource_path('views/layouts/guest.blade.php');
        $layoutContent = file_get_contents($layoutPath);
        
        $this->assertStringContainsString('@livewireStyles', $layoutContent,
            'Guest layout should include @livewireStyles directive.');
        
        $this->assertStringContainsString('@livewireScripts', $layoutContent,
            'Guest layout should include @livewireScripts directive.');
    }
}
