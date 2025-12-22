<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Products;

use App\Models\Branch;
use App\Models\Product;
use App\Models\User;
use App\Services\Contracts\ModuleFieldServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BranchProductSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withExceptionHandling();
        $this->setupPermissions();
    }

    public function test_branch_search_is_scoped_to_current_branch(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $user = $this->userWithPermissions($branchA, ['products.view']);

        Product::factory()->create([
            'branch_id' => $branchA->id,
            'name' => 'Local Product',
            'sku' => 'LOCAL-1',
        ]);

        $foreign = Product::factory()->create([
            'branch_id' => $branchB->id,
            'name' => 'Foreign Product',
            'sku' => 'FOREIGN-1',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/branches/{$branchA->id}/products/search?q={$foreign->sku}");

        $response->assertOk();
        $response->assertJsonPath('meta.pagination.total', 0);
        $response->assertJsonMissing(['id' => $foreign->id]);
    }

    public function test_import_does_not_overwrite_products_in_other_branches(): void
    {
        Storage::fake('local');

        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $user = $this->userWithPermissions($branchA, ['products.import']);

        $foreign = Product::factory()->create([
            'branch_id' => $branchB->id,
            'sku' => 'SHARED-SKU',
            'name' => 'Branch B Name',
            'default_price' => 10,
        ]);

        // Simplify dynamic field resolution during the import
        app()->bind(ModuleFieldServiceInterface::class, function () {
            return new class implements ModuleFieldServiceInterface {
                public function formSchema(string $moduleKey, string $entity, ?int $branchId = null): array
                {
                    return [];
                }

                public function exportColumns(string $moduleKey, string $entity, ?int $branchId = null): array
                {
                    return [];
                }
            };
        });

        $csv = implode("\n", [
            'sku,name,price',
            'SHARED-SKU,Updated Name,50',
            'NEW-SKU,New Product,30',
        ]);

        $file = UploadedFile::fake()->createWithContent('products.csv', $csv);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/branches/{$branchA->id}/products/import", [
            'file' => $file,
        ]);

        $response->assertOk();

        $foreign->refresh();
        $this->assertSame('Branch B Name', $foreign->name);
        $this->assertSame(10.0, (float) $foreign->default_price);

        $this->assertDatabaseHas('products', [
            'branch_id' => $branchA->id,
            'sku' => 'SHARED-SKU',
            'name' => 'Updated Name',
        ]);

        $this->assertDatabaseHas('products', [
            'branch_id' => $branchA->id,
            'sku' => 'NEW-SKU',
            'name' => 'New Product',
        ]);
    }

    public function test_image_upload_rejects_cross_branch_product(): void
    {
        Storage::fake('public');

        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $user = $this->userWithPermissions($branchA, ['products.image.upload']);

        $foreignProduct = Product::factory()->create(['branch_id' => $branchB->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/branches/{$branchA->id}/products/{$foreignProduct->id}/image", [
            'image' => UploadedFile::fake()->image('product.jpg'),
        ]);

        $response->assertStatus(404);
        Storage::disk('public')->assertDirectoryEmpty('product-images');
    }

    protected function setupPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('products.view', 'web');
        Permission::findOrCreate('products.import', 'web');
        Permission::findOrCreate('products.image.upload', 'web');
    }

    protected function userWithPermissions(Branch $branch, array $permissions): User
    {
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->givePermissionTo($permissions);
        $user->branches()->attach($branch);

        return $user;
    }
}
