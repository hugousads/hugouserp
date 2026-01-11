<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Console\Commands\CheckLowStockCommand;
use App\Console\Commands\ClosePosDay;
use App\Http\Controllers\Api\V1\POSController;
use App\Http\Controllers\Api\V1\WebhooksController;
use App\Http\Middleware\EnsurePermission;
use App\Services\POSService;
use ReflectionClass;
use Tests\TestCase;

/**
 * Platform Gap Verification Test
 * 
 * This test verifies that all 5 critical bugs from the "Platform Gap" issue
 * have been resolved by checking that the previously empty files now contain
 * proper implementations.
 */
class PlatformGapVerificationTest extends TestCase
{
    /**
     * Helper method to check if a class constructor injects POSService
     */
    private function assertConstructorInjectsPOSService(ReflectionClass $reflection, string $className = null): void
    {
        $className = $className ?? $reflection->getName();
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor, "{$className} must have constructor");
        
        $parameters = $constructor->getParameters();
        $this->assertGreaterThan(0, count($parameters), "{$className} constructor should have parameters");
        
        // Check if POSService is injected (handle union types properly)
        $hasPosService = false;
        foreach ($parameters as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && $type->getName() === POSService::class) {
                $hasPosService = true;
                break;
            }
        }
        $this->assertTrue($hasPosService, "{$className} must inject POSService");
    }

    /**
     * Bug #1: POSController was empty
     * Verify: Controller exists with all required methods
     */
    public function test_pos_controller_has_required_api_methods(): void
    {
        $reflection = new ReflectionClass(POSController::class);
        
        // Verify class exists and is not abstract
        $this->assertFalse($reflection->isAbstract());
        
        // Verify all required methods exist
        $requiredMethods = [
            'checkout',
            'getCurrentSession',
            'openSession',
            'closeSession',
            'getSessionReport',
        ];
        
        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "POSController must have method: {$method}"
            );
        }
        
        // Verify constructor injects POSService
        $this->assertConstructorInjectsPOSService($reflection);
    }

    /**
     * Bug #2a: ClosePosDay command was empty
     * Verify: Command exists and has proper implementation
     */
    public function test_close_pos_day_command_is_implemented(): void
    {
        $reflection = new ReflectionClass(ClosePosDay::class);
        
        // Verify class exists
        $this->assertTrue($reflection->isInstantiable());
        
        // Verify handle method exists (required for commands)
        $this->assertTrue(
            $reflection->hasMethod('handle'),
            'ClosePosDay must have handle() method'
        );
        
        // Verify signature property exists
        $this->assertTrue(
            $reflection->hasProperty('signature'),
            'Command must have signature property'
        );
        
        // Verify constructor injects POSService
        $this->assertConstructorInjectsPOSService($reflection);
    }

    /**
     * Bug #2b: CheckLowStockCommand was empty
     * Verify: Command exists and has proper implementation
     */
    public function test_check_low_stock_command_is_implemented(): void
    {
        $reflection = new ReflectionClass(CheckLowStockCommand::class);
        
        // Verify class exists
        $this->assertTrue($reflection->isInstantiable());
        
        // Verify handle method exists
        $this->assertTrue(
            $reflection->hasMethod('handle'),
            'CheckLowStockCommand must have handle() method'
        );
        
        // Verify signature property exists
        $this->assertTrue(
            $reflection->hasProperty('signature'),
            'Command must have signature property'
        );
        
        // Get the signature to verify command options
        $signatureProperty = $reflection->getProperty('signature');
        $signatureProperty->setAccessible(true);
        
        // Create instance to read property (using command container)
        $command = $this->app->make(CheckLowStockCommand::class);
        $signature = $signatureProperty->getValue($command);
        
        // Verify command has expected signature parts
        $this->assertStringContainsString('stock:check-low', $signature);
        $this->assertStringContainsString('--branch', $signature);
        $this->assertStringContainsString('--auto-reorder', $signature);
    }

    /**
     * Bug #3: EnsurePermission middleware was empty
     * Verify: Middleware exists with proper handle method
     */
    public function test_ensure_permission_middleware_is_implemented(): void
    {
        $reflection = new ReflectionClass(EnsurePermission::class);
        
        // Verify class exists
        $this->assertTrue($reflection->isInstantiable());
        
        // Verify handle method exists (required for middleware)
        $this->assertTrue(
            $reflection->hasMethod('handle'),
            'EnsurePermission must have handle() method'
        );
        
        // Verify handle method has correct signature
        $handleMethod = $reflection->getMethod('handle');
        $parameters = $handleMethod->getParameters();
        
        // Should have: Request $request, Closure $next, string $abilities, string $mode = 'any'
        $this->assertGreaterThanOrEqual(3, count($parameters), 
            'handle() should accept at least 3 parameters (request, next, abilities)');
        
        // First parameter should be Request (handle union types properly)
        $firstParamType = $parameters[0]->getType();
        if ($firstParamType instanceof \ReflectionNamedType) {
            $this->assertEquals('Illuminate\Http\Request', $firstParamType->getName());
        }
        
        // Second parameter should be Closure (handle union types properly)
        $secondParamType = $parameters[1]->getType();
        if ($secondParamType instanceof \ReflectionNamedType) {
            $this->assertEquals('Closure', $secondParamType->getName());
        }
    }

    /**
     * Bug #4: WebhooksController was empty
     * Verify: Controller exists with webhook handlers for all platforms
     */
    public function test_webhooks_controller_has_platform_handlers(): void
    {
        $reflection = new ReflectionClass(WebhooksController::class);
        
        // Verify class exists
        $this->assertFalse($reflection->isAbstract());
        
        // Verify handlers for all supported platforms exist
        $requiredMethods = [
            'handleShopify',
            'handleWooCommerce',
            'handleLaravel',
        ];
        
        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "WebhooksController must have method: {$method}"
            );
        }
        
        // Verify security verification methods exist
        $securityMethods = [
            'verifyShopifyWebhook',
            'verifyWooCommerceWebhook',
            'verifyLaravelWebhook',
        ];
        
        foreach ($securityMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "WebhooksController must have security method: {$method}"
            );
        }
        
        // Verify anti-replay protection methods exist
        $this->assertTrue(
            $reflection->hasMethod('isFresh'),
            'WebhooksController must have timestamp validation'
        );
        
        $this->assertTrue(
            $reflection->hasMethod('reserveDelivery'),
            'WebhooksController must have replay protection'
        );
    }

    /**
     * Bug #5: Database migrations - verify migrations file exists
     * Note: Actual cascade delete testing requires database setup
     */
    public function test_sales_purchases_migration_file_exists(): void
    {
        $migrationPath = database_path('migrations/2026_01_04_000005_create_sales_purchases_tables.php');
        
        $this->assertFileExists(
            $migrationPath,
            'Sales and purchases migration file must exist'
        );
        
        // Verify file is not empty
        $content = file_get_contents($migrationPath);
        $this->assertNotEmpty($content, 'Migration file should not be empty');
        
        // Verify cascadeOnDelete is used for referential integrity
        $this->assertStringContainsString(
            'cascadeOnDelete()',
            $content,
            'Migration must use cascadeOnDelete() for referential integrity'
        );
        
        // Verify specific critical relationships have cascade deletes (mentioned in bug report)
        // Check for sale_items relationship - allow for whitespace variations
        $this->assertMatchesRegularExpression(
            "/foreignId\s*\(\s*['\"]sale_id['\"]\s*\)\s*->\s*constrained\s*\(\s*\)\s*->\s*cascadeOnDelete\s*\(\s*\)/",
            $content,
            'sale_items must cascade delete when sale is deleted'
        );
    }

    /**
     * Verify POSService is registered in container
     */
    public function test_pos_service_is_registered(): void
    {
        $service = $this->app->make(POSService::class);
        
        $this->assertInstanceOf(
            POSService::class,
            $service,
            'POSService must be available in service container'
        );
    }

    /**
     * Verify commands are registered with Artisan
     */
    public function test_commands_are_registered_with_artisan(): void
    {
        $commands = $this->app['Illuminate\Contracts\Console\Kernel']->all();
        
        // Check ClosePosDay command
        $this->assertArrayHasKey(
            'pos:close-day',
            $commands,
            'pos:close-day command must be registered'
        );
        
        // Check CheckLowStockCommand
        $this->assertArrayHasKey(
            'stock:check-low',
            $commands,
            'stock:check-low command must be registered'
        );
    }

    /**
     * Verify middleware is registered in application
     */
    public function test_permission_middleware_is_registered(): void
    {
        $router = $this->app->make('router');
        
        // Get middleware aliases
        $middlewareAliases = $router->getMiddleware();
        
        $this->assertArrayHasKey(
            'perm',
            $middlewareAliases,
            'Permission middleware must be registered with alias "perm"'
        );
        
        $this->assertEquals(
            EnsurePermission::class,
            $middlewareAliases['perm'],
            'perm alias must point to EnsurePermission middleware'
        );
    }
}
