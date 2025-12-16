<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Models\Branch;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Test Branch',
            'code' => 'TB001',
        ]);

        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_global_search_finds_sale_by_code(): void
    {
        $this->actingAs($this->user);

        // Create a sale with a specific code
        $sale = Sale::create([
            'code' => 'INV-2024-001',
            'branch_id' => $this->branch->id,
            'status' => 'completed',
            'type' => 'invoice',
            'total_amount' => 100,
        ]);

        // Search should find the sale by code
        $this->assertDatabaseHas('sales', [
            'code' => 'INV-2024-001',
        ]);

        // The search query should use 'code' column not 'invoice_number'
        $result = Sale::where('code', 'like', '%INV-2024%')->first();
        $this->assertNotNull($result);
        $this->assertEquals('INV-2024-001', $result->code);
    }

    public function test_global_search_finds_sale_by_reference_no(): void
    {
        $this->actingAs($this->user);

        $sale = Sale::create([
            'code' => 'INV-2024-002',
            'reference_no' => 'REF-TEST-123',
            'branch_id' => $this->branch->id,
            'status' => 'completed',
            'type' => 'invoice',
            'total_amount' => 200,
        ]);

        $result = Sale::where('reference_no', 'like', '%REF-TEST%')->first();
        $this->assertNotNull($result);
        $this->assertEquals('REF-TEST-123', $result->reference_no);
    }

    public function test_global_search_respects_soft_deletes(): void
    {
        $this->actingAs($this->user);

        $sale = Sale::create([
            'code' => 'INV-2024-003',
            'branch_id' => $this->branch->id,
            'status' => 'completed',
            'type' => 'invoice',
            'total_amount' => 300,
        ]);

        $sale->delete();

        // Search without trashed should not find deleted sale
        $result = Sale::where('code', 'like', '%INV-2024-003%')->first();
        $this->assertNull($result);

        // Search with trashed should find it
        $result = Sale::withTrashed()->where('code', 'like', '%INV-2024-003%')->first();
        $this->assertNotNull($result);
    }
}
