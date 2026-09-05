<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Photos for products and their flavours.
 *
 * A flavour may carry its own picture; without one it shows the product's, so
 * the shop only uploads a photo per flavour when the flavours actually differ.
 */
class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private ProductVariant $variant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(ProductImage::DISK);

        $category = Category::create(['name' => 'Graham', 'slug' => 'graham']);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Graham Nest',
            'slug' => 'graham-nest',
            'sku' => 'GN-01',
            'min_reseller_qty' => 1,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'name' => 'Cloudy Classic',
            'slug' => 'cloudy-classic',
            'sku' => 'GN-CL',
            'retail_price' => 13,
            'reseller_price' => 10,
            'stock' => 20,
        ]);

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
    }

    /** @return array<string, mixed> the fields a variant form always sends */
    private function variantPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Cloudy Classic',
            'sku' => 'GN-CL',
            'retail_price' => 13,
            'reseller_price' => 10,
            'stock' => 20,
            'tracks_stock' => true,
            'low_stock_threshold' => 5,
            'is_active' => true,
            'is_available' => true,
        ], $overrides);
    }

    public function test_a_flavour_can_be_given_its_own_photo(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.products.variants.update', [$this->product, $this->variant]), $this->variantPayload([
                'image' => UploadedFile::fake()->image('cloudy.jpg', 800, 800),
            ]))
            ->assertSessionHasNoErrors();

        $path = $this->variant->refresh()->image_path;

        $this->assertNotNull($path);
        $this->assertStringStartsWith(ProductImage::DIR.'/', $path);
        Storage::disk(ProductImage::DISK)->assertExists($path);
    }

    public function test_a_flavour_without_a_photo_falls_back_to_the_product(): void
    {
        $this->product->update(['image_path' => 'products/parent.jpg']);

        $this->assertNull($this->variant->image_path);
        $this->assertStringContainsString('products/parent.jpg', $this->variant->fresh()->image_url);
    }

    public function test_a_flavours_own_photo_wins_over_the_products(): void
    {
        $this->product->update(['image_path' => 'products/parent.jpg']);
        $this->variant->update(['image_path' => 'products/flavour.jpg']);

        $this->assertStringContainsString('products/flavour.jpg', $this->variant->fresh()->image_url);
    }

    public function test_replacing_a_photo_discards_the_old_file(): void
    {
        $this->actingAs($this->admin)->put(
            route('admin.products.variants.update', [$this->product, $this->variant]),
            $this->variantPayload(['image' => UploadedFile::fake()->image('first.jpg')]),
        );

        $first = $this->variant->refresh()->image_path;

        $this->actingAs($this->admin)->put(
            route('admin.products.variants.update', [$this->product, $this->variant]),
            $this->variantPayload(['image' => UploadedFile::fake()->image('second.jpg')]),
        );

        $second = $this->variant->refresh()->image_path;

        $this->assertNotSame($first, $second);
        Storage::disk(ProductImage::DISK)->assertMissing($first);
        Storage::disk(ProductImage::DISK)->assertExists($second);
    }

    public function test_saving_without_touching_the_field_keeps_the_photo(): void
    {
        $this->actingAs($this->admin)->put(
            route('admin.products.variants.update', [$this->product, $this->variant]),
            $this->variantPayload(['image' => UploadedFile::fake()->image('keep.jpg')]),
        );

        $path = $this->variant->refresh()->image_path;

        // An ordinary edit with no file attached must not wipe the picture.
        $this->actingAs($this->admin)
            ->put(
                route('admin.products.variants.update', [$this->product, $this->variant]),
                $this->variantPayload(['retail_price' => 14]),
            )
            ->assertSessionHasNoErrors();

        $this->assertSame($path, $this->variant->refresh()->image_path);
        $this->assertSame('14.00', $this->variant->retail_price);
        Storage::disk(ProductImage::DISK)->assertExists($path);
    }

    public function test_a_photo_can_be_removed_on_purpose(): void
    {
        $this->actingAs($this->admin)->put(
            route('admin.products.variants.update', [$this->product, $this->variant]),
            $this->variantPayload(['image' => UploadedFile::fake()->image('gone.jpg')]),
        );

        $path = $this->variant->refresh()->image_path;

        $this->actingAs($this->admin)
            ->put(
                route('admin.products.variants.update', [$this->product, $this->variant]),
                $this->variantPayload(['remove_image' => true]),
            )
            ->assertSessionHasNoErrors();

        $this->assertNull($this->variant->refresh()->image_path);
        Storage::disk(ProductImage::DISK)->assertMissing($path);
    }

    public function test_deleting_a_flavour_takes_its_photo_with_it(): void
    {
        $this->actingAs($this->admin)->put(
            route('admin.products.variants.update', [$this->product, $this->variant]),
            $this->variantPayload(['image' => UploadedFile::fake()->image('bye.jpg')]),
        );

        $path = $this->variant->refresh()->image_path;

        $this->actingAs($this->admin)
            ->delete(route('admin.products.variants.destroy', [$this->product, $this->variant]))
            ->assertSessionHasNoErrors();

        Storage::disk(ProductImage::DISK)->assertMissing($path);
    }

    public function test_a_new_flavour_can_arrive_with_a_photo(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.products.variants.store', $this->product), $this->variantPayload([
                'name' => 'Misty Green',
                'sku' => 'GN-MG',
                'image' => UploadedFile::fake()->image('matcha.png'),
            ]))
            ->assertSessionHasNoErrors();

        $path = ProductVariant::where('sku', 'GN-MG')->sole()->image_path;

        $this->assertNotNull($path);
        Storage::disk(ProductImage::DISK)->assertExists($path);
    }

    public function test_a_file_that_is_not_an_image_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->put(
                route('admin.products.variants.update', [$this->product, $this->variant]),
                $this->variantPayload(['image' => UploadedFile::fake()->create('prices.pdf', 100)]),
            )
            ->assertSessionHasErrors('image');

        $this->assertNull($this->variant->refresh()->image_path);
    }

    public function test_an_oversized_photo_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->put(
                route('admin.products.variants.update', [$this->product, $this->variant]),
                // 5MB, past the 4MB ceiling.
                $this->variantPayload(['image' => UploadedFile::fake()->image('huge.jpg')->size(5120)]),
            )
            ->assertSessionHasErrors('image');

        $this->assertNull($this->variant->refresh()->image_path);
    }

    public function test_a_product_can_be_given_a_photo_too(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.products.update', $this->product), [
                'name' => 'Graham Nest',
                'sku' => 'GN-01',
                'retail_price' => 0,
                'reseller_price' => 0,
                'stock' => 0,
                'tracks_stock' => true,
                'low_stock_threshold' => 10,
                'min_reseller_qty' => 1,
                'is_available' => true,
                'image' => UploadedFile::fake()->image('nest.webp'),
            ])
            ->assertSessionHasNoErrors();

        $path = $this->product->refresh()->image_path;

        $this->assertNotNull($path);
        Storage::disk(ProductImage::DISK)->assertExists($path);
    }

    public function test_a_typed_external_url_is_never_deleted(): void
    {
        // Paths we did not store point somewhere we do not own.
        $this->variant->update(['image_path' => 'https://example.test/photo.jpg']);

        ProductImage::forget($this->variant->image_path);

        $this->assertSame('https://example.test/photo.jpg', $this->variant->fresh()->image_url);
    }

    public function test_the_browsers_method_spoofed_save_updates_the_flavour(): void
    {
        // This is the shape the form actually posts: a multipart body with the
        // real verb in _method, because PHP will not parse a multipart PUT.
        $this->actingAs($this->admin)
            ->post(
                route('admin.products.variants.update', [$this->product, $this->variant]),
                $this->variantPayload([
                    '_method' => 'put',
                    'reseller_price' => 8,
                    'image' => UploadedFile::fake()->image('cloudy.jpg'),
                ]),
            )
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->variant->refresh();

        $this->assertSame('8.00', $this->variant->reseller_price);
        Storage::disk(ProductImage::DISK)->assertExists($this->variant->image_path);
    }

    public function test_a_spoofed_save_with_no_photo_attached_still_saves(): void
    {
        // The everyday case: change a price, never touch the file field.
        $this->actingAs($this->admin)
            ->post(
                route('admin.products.variants.update', [$this->product, $this->variant]),
                $this->variantPayload([
                    '_method' => 'put',
                    'retail_price' => 15,
                    'reseller_price' => 8,
                    'image' => null,
                    'remove_image' => false,
                ]),
            )
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->variant->refresh();

        $this->assertSame('15.00', $this->variant->retail_price);
        $this->assertSame('8.00', $this->variant->reseller_price);
    }

    public function test_the_products_own_spoofed_save_works_too(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.products.update', $this->product), [
                '_method' => 'put',
                'name' => 'Graham Nest',
                'sku' => 'GN-01',
                'description' => 'Buttery graham layers.',
                'retail_price' => 0,
                'reseller_price' => 0,
                'stock' => 0,
                'tracks_stock' => true,
                'low_stock_threshold' => 10,
                'min_reseller_qty' => 1,
                'is_available' => true,
                'image' => UploadedFile::fake()->image('nest.jpg'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->product->refresh();

        $this->assertSame('Buttery graham layers.', $this->product->description);
        Storage::disk(ProductImage::DISK)->assertExists($this->product->image_path);
    }
}
