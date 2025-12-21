<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Products;

use App\Http\Controllers\Api\V1\ProductsController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProductSearchAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_requires_branch_context(): void
    {
        Permission::findOrCreate('products.view', 'web');

        $user = User::factory()->create(['branch_id' => null]);
        $user->givePermissionTo('products.view');

        $this->actingAs($user, 'web');

        $request = Request::create('/api/v1/products/search', 'GET', [
            'q' => 'ab',
        ]);

        $response = app(ProductsController::class)->search($request);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertEquals('Branch context required', $response->getData(true)['message']);
    }
}
