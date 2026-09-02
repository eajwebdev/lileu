<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Reseller;
use App\Support\Settings;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function index(): Response
    {
        $products = Product::query()
            ->active()
            ->with('category:id,name,slug,accent')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->limit(8)
            ->get();

        return Inertia::render('Site/Landing', [
            'featured' => $products->map(fn (Product $p) => $this->card($p)),
            // Whatever the owner sets up in Admin → Products → Categories shows
            // up here, with a live count and a taste of what is inside.
            'categories' => Category::query()
                ->where('is_active', true)
                ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
                ->with(['products' => fn ($q) => $q->where('is_active', true)
                    ->orderByDesc('is_featured')
                    ->limit(3)])
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Category $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'description' => $c->description,
                    'accent' => $c->accent,
                    'products_count' => $c->products_count,
                    'from_price' => (float) ($c->products->min('retail_price') ?? 0),
                    'preview' => $c->products->map(fn (Product $p) => [
                        'name' => $p->name,
                        'image_url' => $p->image_url,
                    ])->values(),
                ]),
            'stats' => [
                'products' => Product::active()->count(),
                'resellers' => Reseller::approved()->count(),
                'since' => Settings::get('business_tagline', config('lileu.business.tagline')),
            ],
        ]);
    }

    private function card(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'image_url' => $product->image_url,
            'retail_price' => (float) $product->retail_price,
            'reseller_price' => (float) $product->reseller_price,
            'in_stock' => $product->isAvailable(),
            'manually_unavailable' => ! $product->isManuallyAvailable(),
            'made_to_order' => ! $product->tracksStock(),
            'is_featured' => $product->is_featured,
            'category' => $product->category?->only(['name', 'slug', 'accent']),
        ];
    }
}
