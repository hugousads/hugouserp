<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\Contracts\ModuleRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\Contracts\ModuleServiceInterface;
use Tests\TestCase;

/**
 * Test that repositories with observed models can be resolved without circular dependencies.
 * 
 * This test verifies the fix for the infinite recursion issue where ModuleRepository
 * and ProductRepository were causing circular dependency errors during container resolution.
 */
class CircularDependencyTest extends TestCase
{
    public function test_module_repository_can_be_resolved_without_circular_dependency(): void
    {
        // This should not throw an infinite recursion error
        $repository = app(ModuleRepositoryInterface::class);
        
        $this->assertInstanceOf(ModuleRepositoryInterface::class, $repository);
    }

    public function test_product_repository_can_be_resolved_without_circular_dependency(): void
    {
        // This should not throw an infinite recursion error
        $repository = app(ProductRepositoryInterface::class);
        
        $this->assertInstanceOf(ProductRepositoryInterface::class, $repository);
    }

    public function test_module_service_can_be_resolved_without_circular_dependency(): void
    {
        // This should not throw an infinite recursion error
        $service = app(ModuleServiceInterface::class);
        
        $this->assertInstanceOf(ModuleServiceInterface::class, $service);
    }

    public function test_repositories_can_be_resolved_multiple_times_as_singletons(): void
    {
        // Resolve repositories multiple times to ensure they're properly cached as singletons
        $moduleRepo1 = app(ModuleRepositoryInterface::class);
        $moduleRepo2 = app(ModuleRepositoryInterface::class);
        
        // Same instance should be returned (singleton behavior)
        $this->assertSame($moduleRepo1, $moduleRepo2);
        
        $productRepo1 = app(ProductRepositoryInterface::class);
        $productRepo2 = app(ProductRepositoryInterface::class);
        
        // Same instance should be returned (singleton behavior)
        $this->assertSame($productRepo1, $productRepo2);
    }

    public function test_all_repositories_and_services_can_be_resolved_together(): void
    {
        // Resolve all at once to test for any inter-dependency issues
        $moduleRepo = app(ModuleRepositoryInterface::class);
        $productRepo = app(ProductRepositoryInterface::class);
        $moduleService = app(ModuleServiceInterface::class);
        
        $this->assertInstanceOf(ModuleRepositoryInterface::class, $moduleRepo);
        $this->assertInstanceOf(ProductRepositoryInterface::class, $productRepo);
        $this->assertInstanceOf(ModuleServiceInterface::class, $moduleService);
    }
}
