<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $products = Product::query()
            ->with('category:id,name,slug,accent')
            ->when($request->string('q')->toString(), fn ($q, $term) => $q
                ->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%")))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'slug' => $p->slug,
                'description' => $p->description,
                'image_path' => $p->image_path,
                'image_url' => $p->image_url,
                'category_id' => $p->category_id,
                'category' => $p->category?->only(['id', 'name', 'accent']),
                'retail_price' => (float) $p->retail_price,
                'reseller_price' => (float) $p->reseller_price,
                'cost_price' => (float) $p->cost_price,
                'stock' => $p->stock,
                'tracks_stock' => $p->tracksStock(),
                'low_stock_threshold' => $p->low_stock_threshold,
                'min_reseller_qty' => $p->min_reseller_qty,
                'is_active' => $p->is_active,
                'is_featured' => $p->is_featured,
                'available_to_resellers' => $p->available_to_resellers,
                'is_low_stock' => $p->is_low_stock,
            ]);

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'categories' => Category::orderBy('sort_order')->get(['id', 'name', 'slug', 'accent', 'description', 'is_active']),
            'filters' => $request->only('q'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);

        Product::create($data);

        return back()->with('success', 'Product added.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request, $product);

        if ($data['name'] !== $product->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $product->id);
        }

        $product->update($data);

        return back()->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        // Historic orders keep their own name/price snapshot, so removing a
        // product never rewrites a past receipt.
        $product->delete();

        return back()->with('success', 'Product removed.');
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:60', Rule::unique('products', 'sku')->ignore($product?->id)],
            'category_id' => ['nullable', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'image_path' => ['nullable', 'string', 'max:500'],
            'retail_price' => ['required', 'numeric', 'min:0'],
            'reseller_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'tracks_stock' => ['required', 'boolean'],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
            'min_reseller_qty' => ['required', 'integer', 'min:1'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'available_to_resellers' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;

        while (Product::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
