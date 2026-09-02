<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $products = Product::query()
            ->active()
            ->with('category:id,name,slug,accent')
            ->when($request->string('category')->toString(), fn ($q, $slug) => $q->whereHas(
                'category',
                fn ($c) => $c->where('slug', $slug),
            ))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Site/Catalog', [
            'products' => $products->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'description' => $p->description,
                'image_url' => $p->image_url,
                'retail_price' => (float) $p->retail_price,
                'reseller_price' => (float) $p->reseller_price,
                'in_stock' => $p->isAvailable(),
                'made_to_order' => ! $p->tracksStock(),
                'category' => $p->category?->only(['name', 'slug', 'accent']),
            ]),
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug', 'accent']),
            'filters' => $request->only(['category', 'q']),
        ]);
    }

    public function show(Product $product): Response
    {
        abort_unless($product->is_active, 404);

        $product->load('category:id,name,slug,accent');

        return Inertia::render('Site/Product', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'image_url' => $product->image_url,
                'retail_price' => (float) $product->retail_price,
                'reseller_price' => (float) $product->reseller_price,
                'min_reseller_qty' => $product->min_reseller_qty,
                'in_stock' => $product->isAvailable(),
                'made_to_order' => ! $product->tracksStock(),
                'category' => $product->category?->only(['name', 'slug', 'accent']),
            ],
            'related' => Product::active()
                ->where('id', '!=', $product->id)
                ->where('category_id', $product->category_id)
                ->limit(4)
                ->get()
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'image_url' => $p->image_url,
                    'retail_price' => (float) $p->retail_price,
                ]),
        ]);
    }
}
