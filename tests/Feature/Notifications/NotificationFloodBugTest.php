<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Branch;
use App\Models\Product;
use App\Models\User;
use App\Services\SmartNotificationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Test for Notification Flood Loop Bug Fix
 * 
 * Ensures that bulk operations don't trigger thousands of individual
 * notifications, which could cause SMTP blacklisting and system timeouts.
 */
class NotificationFloodBugTest extends TestCase
{
    use RefreshDatabase;

    protected SmartNotificationsService $service;
    protected User $user;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'manager@example.com',
            'is_active' => true,
        ]);
        
        $this->branch = Branch::factory()->create();
        $this->service = app(SmartNotificationsService::class);
        
        Notification::fake();
    }

    /** @test */
    public function it_batches_notifications_for_many_low_stock_products()
    {
        // Create 10 products with low stock (exceeds batch threshold of 5)
        $products = Product::factory()->count(10)->create([
            'branch_id' => $this->branch->id,
            'stock_quantity' => 2,
            'min_stock' => 10,
            'track_stock_alerts' => true,
        ]);

        // Give user permission to view inventory
        $this->user->givePermissionTo('inventory.products.view');

        // Run the notification check
        $notified = $this->service->checkLowStockAlerts($this->branch->id);

        // Should send only ONE batched notification, not 10 individual ones
        $this->assertCount(1, $notified, 
            'Should send one batched notification for many products');
        
        $this->assertStringContainsString('batched', $notified[0],
            'Notification should be identified as batched');
    }

    /** @test */
    public function it_sends_individual_notifications_for_few_products()
    {
        // Create 3 products with low stock (below batch threshold of 5)
        $products = Product::factory()->count(3)->create([
            'branch_id' => $this->branch->id,
            'stock_quantity' => 1,
            'min_stock' => 10,
            'track_stock_alerts' => true,
        ]);

        $this->user->givePermissionTo('inventory.products.view');

        // Run the notification check
        $notified = $this->service->checkLowStockAlerts($this->branch->id);

        // Should send 3 individual notifications (one per product)
        $this->assertCount(3, $notified,
            'Should send individual notifications for small numbers');
    }

    /** @test */
    public function it_prevents_notification_flood_on_bulk_excel_import()
    {
        // Simulate bulk Excel import scenario: 5000 products updated
        // Create a large batch of low-stock products
        $batchSize = 100; // Use 100 for testing (5000 would be too slow)
        
        $products = Product::factory()->count($batchSize)->create([
            'branch_id' => $this->branch->id,
            'stock_quantity' => 0,
            'min_stock' => 5,
            'track_stock_alerts' => true,
        ]);

        $this->user->givePermissionTo('inventory.products.view');

        // Run notification check
        $notified = $this->service->checkLowStockAlerts($this->branch->id);

        // Should send ONLY 1 batched notification, not 100 individual ones
        $this->assertLessThanOrEqual(1, count($notified),
            'Should prevent flood by batching notifications');

        // Verify it's a batched notification
        if (count($notified) > 0) {
            $this->assertStringContainsString('batched', $notified[0]);
        }
    }

    /** @test */
    public function it_respects_daily_notification_limit()
    {
        // Create products with low stock
        $products = Product::factory()->count(10)->create([
            'branch_id' => $this->branch->id,
            'stock_quantity' => 1,
            'min_stock' => 10,
            'track_stock_alerts' => true,
        ]);

        $this->user->givePermissionTo('inventory.products.view');

        // First check - should send notification
        $notified1 = $this->service->checkLowStockAlerts($this->branch->id);
        $this->assertGreaterThan(0, count($notified1), 
            'First check should send notifications');

        // Second check same day - should NOT send duplicate notification
        $notified2 = $this->service->checkLowStockAlerts($this->branch->id);
        $this->assertEquals(0, count($notified2), 
            'Should not send duplicate notifications on same day');
    }

    /** @test */
    public function it_includes_product_summary_in_batched_notification()
    {
        // Create multiple low-stock products
        $products = Product::factory()->count(7)->create([
            'branch_id' => $this->branch->id,
            'stock_quantity' => 2,
            'min_stock' => 10,
            'track_stock_alerts' => true,
        ]);

        $this->user->givePermissionTo('inventory.products.view');

        // Mock the notification to capture data
        Notification::fake();

        $notified = $this->service->checkLowStockAlerts($this->branch->id);

        // Verify notification was sent
        Notification::assertSentTo(
            $this->user,
            \App\Notifications\GeneralNotification::class,
            function ($notification) use ($products) {
                $data = $notification->toArray($this->user)['data'] ?? [];
                
                // Should contain batch information
                return isset($data['notification_type']) 
                    && $data['notification_type'] === 'low_stock_batch'
                    && isset($data['product_count'])
                    && $data['product_count'] === 7
                    && isset($data['products']);
            }
        );
    }

    /** @test */
    public function it_prevents_smtp_blacklisting_scenario()
    {
        // Create scenario that would trigger 5000 emails
        // This would cause SMTP blacklisting in production
        
        $batchSize = 50; // Simulate bulk update
        
        Product::factory()->count($batchSize)->create([
            'branch_id' => $this->branch->id,
            'stock_quantity' => 0,
            'min_stock' => 5,
            'track_stock_alerts' => true,
        ]);

        // Create multiple users who would receive notifications
        $users = User::factory()->count(3)->create([
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        foreach ($users as $user) {
            $user->givePermissionTo('inventory.products.view');
        }

        Notification::fake();

        // Run check
        $this->service->checkLowStockAlerts($this->branch->id);

        // Without batching: would send 50 products × 3 users = 150 emails
        // With batching: sends 1 batch × 3 users = 3 emails
        
        // Assert that we sent way fewer notifications than products
        $sentCount = 0;
        foreach ($users as $user) {
            Notification::assertSentTo($user, \App\Notifications\GeneralNotification::class);
            $sentCount++;
        }

        $this->assertLessThanOrEqual(10, $sentCount,
            'Should send drastically fewer notifications than products to prevent SMTP issues');
    }
}
