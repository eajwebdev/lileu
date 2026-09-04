<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ProductImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Flavours of a product, each with its own price and stock.
 */
class ProductVariantController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request);
        $data['product_id'] = $product->id;
        $data['slug'] = $this->uniqueSlug($product, $data['name']);
        $data['image_path'] = $this->image($request);

        ProductVariant::create($data);

        return back()->with('success', "{$data['name']} added to {$product->name}.");
    }

    public function update(Request $request, Product $product, ProductVariant $variant): RedirectResponse
    {
        abort_unless($variant->product_id === $product->id, 404);

        $data = $this->validated($request, $variant);

        if ($data['name'] !== $variant->name) {
            $data['slug'] = $this->uniqueSlug($product, $data['name'], $variant->id);
        }

        $data['image_path'] = $this->image($request, $variant->image_path);

        $variant->update($data);

        return back()->with('success', "{$variant->name} updated.");
    }

    public function destroy(Product $product, ProductVariant $variant): RedirectResponse
    {
        abort_unless($variant->product_id === $product->id, 404);

        // Past receipts keep their own name and price snapshot, so removing a
        // flavour never rewrites what was already sold.
        $name = $variant->name;
        ProductImage::forget($variant->image_path);
        $variant->delete();

        return back()->with('success', "{$name} removed from {$product->name}.");
    }

    /** The quick switch a cashier's floor needs when something runs out. */
    public function availability(Request $request, Product $product, ProductVariant $variant): RedirectResponse
    {
        abort_unless($variant->product_id === $product->id, 404);

        $data = $request->validate(['is_available' => ['required', 'boolean']]);

        $variant->update($data);

        return back()->with(
            'success',
            $variant->isManuallyAvailable()
                ? "{$variant->name} is available again."
                : "{$variant->name} is now marked unavailable.",
        );
    }

    private function validated(Request $request, ?ProductVariant $variant = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:60', Rule::unique('product_variants', 'sku')->ignore($variant?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'image' => ProductImage::RULES,
            'remove_image' => ['nullable', 'boolean'],
            'retail_price' => ['required', 'numeric', 'min:0'],
            'reseller_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'tracks_stock' => ['required', 'boolean'],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'is_available' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    /**
     * The picture to save: a new upload, an explicit removal, or whatever the
     * flavour already had. Submitting the form without touching the file field
     * must never quietly drop the existing image.
     */
    private function image(Request $request, ?string $current = null): ?string
    {
        if ($request->hasFile('image')) {
            return ProductImage::put($request->file('image'), $current);
        }

        if ($request->boolean('remove_image')) {
            ProductImage::forget($current);

            return null;
        }

        return $current;
    }

    private function uniqueSlug(Product $product, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'variant';
        $slug = $base;
        $i = 2;

        while (ProductVariant::where('product_id', $product->id)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
