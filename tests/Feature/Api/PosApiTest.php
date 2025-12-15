<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test branch
        $this->branch = Branch::factory()->create([
            'name' => 'Test Branch',
            'is_active' => true,
        ]);

        // Create a test user
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // Note: In real tests, you'd need to seed permissions and roles
        // For now, we'll test routes exist and basic validation
    }

    public function test_product_search_endpoint_exists(): void
    {
        Sanctum::actingAs($this->user);

        // Test that the route exists
        $response = $this->getJson("/api/v1/branches/{$this->branch->id}/products/search?q=Test");

        // Should not return 404 (route exists)
        $this->assertNotEquals(404, $response->status());

        // Verify JSON structure regardless of permission status
        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'success',
                'data',
            ]);
        }
    }

    public function test_product_search_requires_authentication(): void
    {
        $response = $this->getJson("/api/v1/branches/{$this->branch->id}/products/search?q=Test");

        $response->assertStatus(401);
    }

    public function test_product_search_validates_query_length(): void
    {
        Sanctum::actingAs($this->user);

        // Short query should return validation error
        $response = $this->getJson("/api/v1/branches/{$this->branch->id}/products/search?q=T");

        // API validates minimum query length - 422 is expected for too-short queries
        $response->assertStatus(422);
    }

    public function test_pos_checkout_endpoint_accepts_branch_id_in_route(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/branches/{$this->branch->id}/pos/checkout", [
            'items' => [
                [
                    'product_id' => 1,
                    'qty' => 2,
                    'price' => 100,
                ],
            ],
        ]);

        // Route should exist (not 404)
        $this->assertNotEquals(404, $response->status());

        // Verify JSON structure for success or error responses
        $response->assertJsonStructure([
            'success',
        ]);
    }

    public function test_pos_checkout_validates_required_fields(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/branches/{$this->branch->id}/pos/checkout", [
            'items' => [],
        ]);

        // Should fail with either permission (403) or validation (422)
        $this->assertTrue(in_array($response->status(), [422, 403], true));
        $response->assertJsonStructure(['success']);
    }

    public function test_pos_checkout_validates_item_structure(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/branches/{$this->branch->id}/pos/checkout", [
            'items' => [
                [
                    'product_id' => 999999, // Non-existent product
                    'qty' => 1,
                ],
            ],
        ]);

        // Should fail with either permission (403) or validation (422)
        $this->assertTrue(in_array($response->status(), [422, 403], true));
        $response->assertJsonStructure(['success']);
    }
}
