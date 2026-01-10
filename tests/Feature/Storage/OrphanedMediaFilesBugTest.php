<?php

declare(strict_types=1);

namespace Tests\Feature\Storage;

use App\Models\Module;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Test for Orphaned Media Files Bug Fix
 * 
 * Ensures that when entities are deleted, their associated media files
 * are also deleted to prevent accumulation of "zombie" files.
 */
class OrphanedMediaFilesBugTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Use fake storage for testing
        Storage::fake('public');
    }

    /** @test */
    public function it_deletes_product_image_when_product_is_deleted()
    {
        // Create a fake image file
        $image = UploadedFile::fake()->image('product.jpg');
        $imagePath = $image->store('products', 'public');

        // Create product with image
        $product = Product::factory()->create([
            'image' => $imagePath,
        ]);

        // Verify file exists
        Storage::disk('public')->assertExists($imagePath);

        // Delete product
        $product->delete();

        // File should be deleted
        Storage::disk('public')->assertMissing($imagePath);
    }

    /** @test */
    public function it_deletes_product_thumbnail_when_product_is_deleted()
    {
        $thumbnail = UploadedFile::fake()->image('thumbnail.jpg');
        $thumbnailPath = $thumbnail->store('products/thumbnails', 'public');

        $product = Product::factory()->create([
            'thumbnail' => $thumbnailPath,
        ]);

        Storage::disk('public')->assertExists($thumbnailPath);

        $product->delete();

        Storage::disk('public')->assertMissing($thumbnailPath);
    }

    /** @test */
    public function it_deletes_product_gallery_images_when_product_is_deleted()
    {
        // Create multiple gallery images
        $image1 = UploadedFile::fake()->image('gallery1.jpg');
        $image2 = UploadedFile::fake()->image('gallery2.jpg');
        $image3 = UploadedFile::fake()->image('gallery3.jpg');

        $path1 = $image1->store('products/gallery', 'public');
        $path2 = $image2->store('products/gallery', 'public');
        $path3 = $image3->store('products/gallery', 'public');

        $product = Product::factory()->create([
            'gallery' => [$path1, $path2, $path3],
        ]);

        // Verify all files exist
        Storage::disk('public')->assertExists($path1);
        Storage::disk('public')->assertExists($path2);
        Storage::disk('public')->assertExists($path3);

        // Delete product
        $product->delete();

        // All gallery images should be deleted
        Storage::disk('public')->assertMissing($path1);
        Storage::disk('public')->assertMissing($path2);
        Storage::disk('public')->assertMissing($path3);
    }

    /** @test */
    public function it_deletes_all_product_media_when_product_is_deleted()
    {
        // Create all types of media
        $image = UploadedFile::fake()->image('main.jpg');
        $thumbnail = UploadedFile::fake()->image('thumb.jpg');
        $gallery1 = UploadedFile::fake()->image('gal1.jpg');
        $gallery2 = UploadedFile::fake()->image('gal2.jpg');

        $imagePath = $image->store('products', 'public');
        $thumbPath = $thumbnail->store('products/thumbnails', 'public');
        $gal1Path = $gallery1->store('products/gallery', 'public');
        $gal2Path = $gallery2->store('products/gallery', 'public');

        $product = Product::factory()->create([
            'image' => $imagePath,
            'thumbnail' => $thumbPath,
            'images' => [$gal1Path],
            'gallery' => [$gal2Path],
        ]);

        // Verify all exist
        Storage::disk('public')->assertExists($imagePath);
        Storage::disk('public')->assertExists($thumbPath);
        Storage::disk('public')->assertExists($gal1Path);
        Storage::disk('public')->assertExists($gal2Path);

        // Delete product
        $product->delete();

        // All should be deleted
        Storage::disk('public')->assertMissing($imagePath);
        Storage::disk('public')->assertMissing($thumbPath);
        Storage::disk('public')->assertMissing($gal1Path);
        Storage::disk('public')->assertMissing($gal2Path);
    }

    /** @test */
    public function it_deletes_module_icon_when_module_is_deleted()
    {
        $icon = UploadedFile::fake()->image('icon.png');
        $iconPath = $icon->store('modules/icons', 'public');

        $module = Module::factory()->create([
            'icon' => $iconPath,
        ]);

        Storage::disk('public')->assertExists($iconPath);

        $module->delete();

        Storage::disk('public')->assertMissing($iconPath);
    }

    /** @test */
    public function it_handles_missing_files_gracefully_on_deletion()
    {
        // Create product with file path that doesn't actually exist
        $product = Product::factory()->create([
            'image' => 'products/nonexistent.jpg',
            'thumbnail' => 'products/thumbnails/missing.jpg',
        ]);

        // Files don't exist
        Storage::disk('public')->assertMissing('products/nonexistent.jpg');
        Storage::disk('public')->assertMissing('products/thumbnails/missing.jpg');

        // Delete should not throw exception
        $this->expectNotToPerformAssertions();
        $product->delete();
    }

    /** @test */
    public function it_prevents_terabytes_of_zombie_files_accumulation()
    {
        // Simulate years of deletions without cleanup
        // Create and delete 100 products with media
        
        $createdFiles = [];
        
        for ($i = 0; $i < 100; $i++) {
            $image = UploadedFile::fake()->image("product{$i}.jpg");
            $imagePath = $image->store('products', 'public');
            $createdFiles[] = $imagePath;

            $product = Product::factory()->create([
                'image' => $imagePath,
            ]);

            // Verify file was created
            Storage::disk('public')->assertExists($imagePath);

            // Delete product
            $product->delete();

            // File should be deleted (preventing orphan)
            Storage::disk('public')->assertMissing($imagePath);
        }

        // After 100 deletions, storage should be clean (no orphans)
        $remainingFiles = Storage::disk('public')->allFiles('products');
        
        $this->assertEmpty($remainingFiles, 
            'No orphaned files should remain after entity deletions');
    }

    /** @test */
    public function it_only_deletes_files_for_deleted_entity_not_others()
    {
        // Create two products with different images
        $image1 = UploadedFile::fake()->image('product1.jpg');
        $image2 = UploadedFile::fake()->image('product2.jpg');
        
        $path1 = $image1->store('products', 'public');
        $path2 = $image2->store('products', 'public');

        $product1 = Product::factory()->create(['image' => $path1]);
        $product2 = Product::factory()->create(['image' => $path2]);

        Storage::disk('public')->assertExists($path1);
        Storage::disk('public')->assertExists($path2);

        // Delete only product1
        $product1->delete();

        // Only path1 should be deleted, path2 should remain
        Storage::disk('public')->assertMissing($path1);
        Storage::disk('public')->assertExists($path2);
    }
}
