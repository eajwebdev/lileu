<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\Catalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $products = Product::query()
            ->active()
            ->with(['category:id,name,slug,accent', 'variants'])
            ->when($request->string('category')->toString(), fn ($q, $slug) => $q->whereHas(
                'category',
                fn ($c) => $c->where('slug', $slug),
            ))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Site/Catalog', [
            // Shoppers browse products and choose a flavour on the product
            // page, so the grid stays one card per product.
            'products' => Catalog::grouped($products),
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug', 'accent']),
            'filters' => $request->only(['category', 'q']),
        ]);
    }

    public function show(Product $product): Response
    {
        abort_unless($product->is_active, 404);

        $product->load(['category:id,name,slug,accent', 'variants']);

        return Inertia::render('Site/Product', [
            'product' => Catalog::grouped([$product])[0] + [
                'min_reseller_qty' => $product->min_reseller_qty,
            ],
            'related' => Product::active()
                ->where('id', '!=', $product->id)
                ->where('category_id', $product->category_id)
                ->with('variants')
                ->limit(4)
                ->get()
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'image_url' => $p->image_url,
                    'retail_price' => $p->lowestRetailPrice(),
                ]),
        ]);
    }
}
