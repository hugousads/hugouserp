<?php

declare(strict_types=1);

namespace Tests\Feature\POS;

use App\Models\Branch;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\POSService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class SessionValidationTest extends TestCase
{
    use RefreshDatabase;

    protected POSService $service;
    protected Branch $branch;
    protected User $user;
    protected Product $product;
    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(POSService::class);

        $this->branch = Branch::create([
            'name' => 'Test Branch',
            'code' => 'TB001',
        ]);

        $this->warehouse = Warehouse::create([
            'name' => 'Default Warehouse',
            'code' => 'WH001',
            'branch_id' => $this->branch->id,
            'is_default' => true,
            'status' => 'active',
        ]);

        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->product = Product::create([
            'name' => 'Test Product',
            'code' => 'PRD001',
            'sku' => 'SKU001',
            'type' => 'product',
            'product_type' => 'physical',
            'default_price' => 100,
            'standard_cost' => 50,
            'branch_id' => $this->branch->id,
        ]);

        // Authenticate as user
        $this->actingAs($this->user);

        // Set branch context
        request()->attributes->set('branch_id', $this->branch->id);

        // Enable session requirement
        config(['pos.require_session' => true]);
    }

    public function test_checkout_fails_without_active_pos_session(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('No active POS session. Please open a session first.');

        $payload = [
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'channel' => 'pos',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'qty' => 1,
                    'price' => 100,
                ],
            ],
        ];

        $this->service->checkout($payload);
    }

    public function test_checkout_succeeds_with_active_pos_session(): void
    {
        // Create an active POS session
        PosSession::create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'opening_cash' => 100,
            'status' => PosSession::STATUS_OPEN,
            'opened_at' => now(),
        ]);

        $payload = [
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'channel' => 'pos',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'qty' => 1,
                    'price' => 100,
                ],
            ],
        ];

        $result = $this->service->checkout($payload);

        $this->assertInstanceOf(\App\Models\Sale::class, $result);
        $this->assertEquals(100, $result->grand_total);
    }

    public function test_checkout_succeeds_when_session_requirement_disabled(): void
    {
        config(['pos.require_session' => false]);

        $payload = [
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'channel' => 'pos',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'qty' => 1,
                    'price' => 100,
                ],
            ],
        ];

        $result = $this->service->checkout($payload);

        $this->assertInstanceOf(\App\Models\Sale::class, $result);
        $this->assertEquals(100, $result->grand_total);
    }

    public function test_checkout_succeeds_for_non_pos_channel(): void
    {
        $payload = [
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'channel' => 'online',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'qty' => 1,
                    'price' => 100,
                ],
            ],
        ];

        $result = $this->service->checkout($payload);

        $this->assertInstanceOf(\App\Models\Sale::class, $result);
        $this->assertEquals(100, $result->grand_total);
    }
}
